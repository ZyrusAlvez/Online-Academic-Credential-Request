<?php

namespace App\Services;

use App\Enums\DelayCategory;
use App\Enums\DocumentKind;
use App\Enums\IdentityStatus;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequesterMode;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Events\RequestEventRecorded;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Models\PaymentProof;
use App\Models\RequestDeficiency;
use App\Models\RequestEvent;
use App\Models\RequestRepresentative;
use App\Models\RequestStep;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single place where credential requests, their route steps and office visits change
 * status (specs 04, 05, 11).
 *
 * Every public method runs in a transaction, locks the request row, checks that the action is
 * allowed in the current status, and appends a ledger entry (request_events). Listeners are
 * notified after commit via RequestEventRecorded.
 */
class RequestWorkflow
{
    public function __construct(private readonly WorkingCalendar $calendar) {}

    /** Private disk for receipts, IDs and SPAs: local or Cloudflare R2 (config uphsl.receipts_disk). */
    public static function proofDisk(): string
    {
        return (string) config('uphsl.receipts_disk', 'local');
    }

    // ── Submission (spec 04) ───────────────────────────────────────────────────────────────

    /**
     * @param  array{credential_type_id: int, copies: int, purpose: string, receiving_institution?: ?string}  $data
     */
    public function submit(User $owner, array $data): CredentialRequest
    {
        if (! $owner->canRequestCredentials()) {
            throw new InvalidTransition('Complete your profile and upload a valid ID before requesting a credential.');
        }

        return DB::transaction(function () use ($owner, $data): CredentialRequest {
            $type = CredentialType::requestable()->findOrFail($data['credential_type_id']);
            $receivingInstitution = $data['receiving_institution'] ?? null;

            if ($type->requires_receiving_institution && blank($receivingInstitution)) {
                throw new InvalidTransition('Give the complete name of the receiving school.');
            }

            $request = new CredentialRequest([
                'credential_type_id' => $type->id,
                'copies' => $data['copies'],
                'purpose' => $data['purpose'],
                'receiving_institution' => $type->requires_receiving_institution ? $receivingInstitution : null,
            ]);

            $request->forceFill([
                'user_id' => $owner->id,
                'college_id' => $owner->college_id,
                'academic_status' => $owner->academic_status,
                'program' => $owner->program,
                'student_number' => $owner->student_number,
                'attended_from' => $owner->attended_from,
                'attended_to' => $owner->attended_to,
                'requester_mode' => RequesterMode::Personal,
                'requires_payment' => $type->requires_payment,
                'fee' => $type->requires_payment ? $type->fee : null,
                'amount_due' => $type->requires_payment && $type->fee !== null
                    ? number_format((float) $type->fee * $data['copies'], 2, '.', '')
                    : null,
                'status' => RequestStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            $request->forceFill([
                'reference_no' => sprintf('CR-%s-%06d', $request->created_at->format('Y'), $request->id),
            ])->save();

            $this->record($request, $owner, RequestEventType::Submitted, null, $request->status);

            return $request;
        });
    }

    // ── Evaluation and compliance (spec 04) ────────────────────────────────────────────────

    public function startEvaluation(CredentialRequest $request, User $registrar): void
    {
        DB::transaction(function () use ($request, $registrar): void {
            $locked = $this->lock($request, [RequestStatus::Submitted], 'Only submitted requests can be taken up for evaluation.');

            $locked->forceFill(['evaluation_started_at' => now(), 'evaluated_by' => $registrar->id]);
            $this->transition($locked, $request, RequestStatus::UnderEvaluation, $registrar, RequestEventType::EvaluationStarted);
        });
    }

    /**
     * Requirements incomplete: record what is missing (REQ-04-06, 09).
     *
     * @param  list<string>  $deficiencies
     */
    public function requestCompliance(CredentialRequest $request, User $registrar, array $deficiencies, ?string $remarks = null): void
    {
        $deficiencies = array_values(array_filter(array_map(trim(...), $deficiencies), filled(...)));

        if ($deficiencies === []) {
            throw new InvalidTransition('List at least one missing requirement.');
        }

        DB::transaction(function () use ($request, $registrar, $deficiencies, $remarks): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::UnderEvaluation, RequestStatus::PendingCompliance],
                'Requirements can only be marked incomplete during evaluation.',
            );

            foreach ($deficiencies as $description) {
                $deficiency = $locked->deficiencies()->make(['description' => Str::limit($description, 250, '')]);
                $deficiency->forceFill(['recorded_by' => $registrar->id])->save();
            }

            $summary = 'Missing: '.implode('; ', $deficiencies).(filled($remarks) ? "\n{$remarks}" : '');

            $locked->status === RequestStatus::PendingCompliance
                ? $this->record($locked, $registrar, RequestEventType::ComplianceRequested, null, null, $summary)
                : $this->transition($locked, $request, RequestStatus::PendingCompliance, $registrar, RequestEventType::ComplianceRequested, $summary);
        });
    }

    /**
     * Record the physical submission or clearance of one missing requirement (REQ-04-10).
     */
    public function resolveDeficiency(RequestDeficiency $deficiency, User $registrar, string $resolution): void
    {
        if (blank($resolution)) {
            throw new InvalidTransition('Describe what was received or the clearance status.');
        }

        DB::transaction(function () use ($deficiency, $registrar, $resolution): void {
            $locked = $this->lock($deficiency->credentialRequest()->firstOrFail(), [RequestStatus::PendingCompliance], 'This request is not waiting for compliance.');

            /** @var RequestDeficiency $current */
            $current = RequestDeficiency::whereKey($deficiency->id)->lockForUpdate()->firstOrFail();

            if ($current->resolved_at !== null) {
                throw new InvalidTransition('This requirement has already been complied with.');
            }

            $current->forceFill(['resolved_at' => now(), 'resolved_by' => $registrar->id, 'resolution' => $resolution])->save();
            $deficiency->setRawAttributes($current->getAttributes(), sync: true);

            $this->record($locked, $registrar, RequestEventType::DeficiencyResolved, null, null, "{$current->description}: {$resolution}");
        });
    }

    /**
     * Requirements complete: payment instructions for a paid request, acceptance for a free one
     * (REQ-04-07, 08).
     */
    public function completeEvaluation(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::UnderEvaluation, RequestStatus::PendingCompliance],
                'Only requests under evaluation or pending compliance can be marked complete.',
            );

            if ($locked->student()->firstOrFail()->identity_status !== IdentityStatus::Verified) {
                throw new InvalidTransition("Verify the requester's valid ID before marking the requirements complete.");
            }

            if ($locked->deficiencies()->whereNull('resolved_at')->exists()) {
                throw new InvalidTransition('Record the compliance of every missing requirement first.');
            }

            $locked->requirements_completed_at = now();

            if ($locked->requires_payment) {
                $this->transition($locked, $request, RequestStatus::AwaitingPayment, $registrar, RequestEventType::RequirementsCompleted, $remarks);

                return;
            }

            $locked->save();
            $this->record($locked, $registrar, RequestEventType::RequirementsCompleted, null, null, $remarks);
            $this->accept($locked, $request, $registrar);
        });
    }

    /**
     * Ask the requester to clarify or act (REQ-04-11). After acceptance this pauses the
     * processing period (REQ-05-13).
     */
    public function requestCustomerAction(CredentialRequest $request, User $registrar, string $message): void
    {
        if (blank($message)) {
            throw new InvalidTransition('Tell the requester what is needed.');
        }

        DB::transaction(function () use ($request, $registrar, $message): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::UnderEvaluation, RequestStatus::PendingCompliance, RequestStatus::AcceptedForProcessing, RequestStatus::InProgress, RequestStatus::ForFinalApproval],
                'The requester cannot be asked to act on this request now.',
            );

            if ($locked->accepted_at !== null) {
                $pause = $locked->pauses()->make();
                $pause->forceFill([
                    'reason' => $message,
                    'resume_status' => $locked->status,
                    'started_at' => now(),
                    'started_by' => $registrar->id,
                ])->save();
            }

            $this->transition($locked, $request, RequestStatus::ReturnedForCustomerAction, $registrar, RequestEventType::CustomerActionRequested, $message);
        });
    }

    /**
     * The requester replied, or the registrar recorded that they complied: back to where the
     * request was. Closing a pause sets the adjusted due date (REQ-04-11, REQ-05-13).
     */
    public function customerResponded(CredentialRequest $request, User $actor, ?string $remarks = null): void
    {
        if ($actor->hasRole(Role::Student) && blank($remarks)) {
            throw new InvalidTransition('Write your reply to the Office of the Registrar.');
        }

        DB::transaction(function () use ($request, $actor, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::ReturnedForCustomerAction], 'This request is not waiting for the requester.');

            $pause = $locked->pauses()->whereNull('ended_at')->latest('id')->first();

            if ($pause !== null) {
                $days = $this->calendar->workingDaysBetween($pause->started_at, now());
                $pause->forceFill(['ended_at' => now(), 'ended_by' => $actor->id, 'working_days' => $days])->save();

                if ($days > 0 && $locked->effective_due_at !== null) {
                    $locked->adjusted_due_at = $this->calendar->addWorkingDays($locked->effective_due_at, $days);
                }

                $resumeTo = $pause->resume_status;
            } else {
                /** @var ?RequestEvent $asked */
                $asked = $locked->events()->where('type', RequestEventType::CustomerActionRequested)->reorder('id', 'desc')->first();
                $resumeTo = $asked->from_status ?? RequestStatus::UnderEvaluation;
            }

            $this->transition($locked, $request, $resumeTo, $actor, RequestEventType::CustomerResponded, $remarks);
        });
    }

    /**
     * Not eligible (REQ-04-06).
     */
    public function decline(CredentialRequest $request, User $registrar, string $reason): void
    {
        if (blank($reason)) {
            throw new InvalidTransition('A reason is required to decline a request.');
        }

        DB::transaction(function () use ($request, $registrar, $reason): void {
            $locked = $this->lock(
                $request,
                [...RequestStatus::beforeAcceptance(), RequestStatus::ReturnedForCustomerAction],
                'Only requests that have not been accepted can be declined.',
            );

            if ($locked->accepted_at !== null) {
                throw new InvalidTransition('An accepted request cannot be declined; cancel it instead.');
            }

            $locked->forceFill(['declined_at' => now(), 'decline_reason' => $reason]);
            $this->transition($locked, $request, RequestStatus::Declined, $registrar, RequestEventType::Declined, $reason);
        });
    }

    public function changeCollege(CredentialRequest $request, User $registrar, Department $college): void
    {
        DB::transaction(function () use ($request, $registrar, $college): void {
            $locked = $this->lock(
                $request,
                [...RequestStatus::beforeAcceptance(), RequestStatus::ReturnedForCustomerAction],
                'The college can only be changed before the request is accepted.',
            );

            if ($locked->accepted_at !== null) {
                throw new InvalidTransition('The college can only be changed before the request is accepted.');
            }

            $previous = $locked->college()->firstOrFail();

            if ($previous->is($college)) {
                return;
            }

            $locked->college_id = $college->id;
            $locked->save();
            $request->college_id = $college->id;
            $request->unsetRelation('college');

            $this->record($locked, $registrar, RequestEventType::CollegeChanged, null, null, "From {$previous->name} to {$college->name}.");
        });
    }

    // ── Payment and acceptance (spec 04) ───────────────────────────────────────────────────

    public function submitPaymentProof(
        CredentialRequest $request,
        User $owner,
        UploadedFile $file,
        ?string $referenceNo = null,
        ?string $amount = null,
        ?string $paidOn = null,
    ): PaymentProof {
        $path = $this->store($file, "payment-proofs/{$request->id}");

        try {
            return DB::transaction(function () use ($request, $owner, $file, $path, $referenceNo, $amount, $paidOn): PaymentProof {
                $locked = $this->lock($request, [RequestStatus::AwaitingPayment], 'A proof of payment can only be uploaded while the request is awaiting payment.');

                $proof = $locked->paymentProofs()->create([
                    'uploaded_by' => $owner->id,
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'reference_no' => filled($referenceNo) ? $referenceNo : null,
                    'amount' => filled($amount) ? $amount : null,
                    'paid_on' => filled($paidOn) ? $paidOn : null,
                    'status' => PaymentStatus::Submitted,
                ]);

                $this->transition($locked, $request, RequestStatus::PaymentVerification, $owner, RequestEventType::PaymentSubmitted);

                return $proof;
            });
        } catch (Throwable $e) {
            Storage::disk(self::proofDisk())->delete($path);

            throw $e;
        }
    }

    public function rejectPayment(CredentialRequest $request, User $registrar, string $remarks): void
    {
        if (blank($remarks)) {
            throw new InvalidTransition('Remarks are required to reject a payment.');
        }

        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PaymentVerification], 'Only payments awaiting verification can be rejected.');

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Rejected, remarks: $remarks);
            $this->transition($locked, $request, RequestStatus::AwaitingPayment, $registrar, RequestEventType::PaymentRejected, $remarks);
        });
    }

    /**
     * The payment matches the official record: accept the request for processing (REQ-04-13, 14).
     */
    public function verifyPaymentAndAccept(CredentialRequest $request, User $registrar, string $officialReceiptNo, ?string $remarks = null): void
    {
        if (blank($officialReceiptNo)) {
            throw new InvalidTransition('Enter the official receipt number from the payment record.');
        }

        DB::transaction(function () use ($request, $registrar, $officialReceiptNo, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PaymentVerification], 'Only payments awaiting verification can be verified.');

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Verified, $officialReceiptNo, $remarks);
            $this->record($locked, $registrar, RequestEventType::PaymentVerified, null, null, "Official receipt {$officialReceiptNo}");
            $this->accept($locked, $request, $registrar, $remarks);
        });
    }

    /**
     * Owners may cancel before acceptance; the registrar may cancel any open request (REQ-04-16).
     */
    public function cancel(CredentialRequest $request, User $actor, ?string $reason = null): void
    {
        $isRegistrar = $actor->hasRole(Role::Registrar);

        if ($isRegistrar && blank($reason)) {
            throw new InvalidTransition('A reason is required to cancel a request.');
        }

        DB::transaction(function () use ($request, $actor, $reason, $isRegistrar): void {
            $locked = $this->lock($request, RequestStatus::open(), 'This request is already closed.');

            if (! $isRegistrar && ($locked->accepted_at !== null || ! in_array($locked->status, [...RequestStatus::beforeAcceptance(), RequestStatus::ReturnedForCustomerAction], true))) {
                throw new InvalidTransition('This request can no longer be cancelled because it is already being processed.');
            }

            $this->closeOpenVisit($locked, $actor);
            $locked->forceFill(['cancelled_at' => now(), 'cancel_reason' => filled($reason) ? $reason : null]);
            $this->transition($locked, $request, RequestStatus::Cancelled, $actor, RequestEventType::Cancelled, $reason);
        });
    }

    // ── Office routing (spec 05) ───────────────────────────────────────────────────────────

    /**
     * The office confirms the document physically arrived (REQ-05-03).
     */
    public function acceptReceipt(OfficeVisit $visit, User $actor): void
    {
        DB::transaction(function () use ($visit, $actor): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::InTransit]);

            $current->forceFill(['status' => VisitStatus::Received, 'received_at' => now(), 'received_by' => $actor->id])->save();
            $visit->setRawAttributes($current->getAttributes(), sync: true);

            if ($current->purpose === VisitPurpose::Correction) {
                $this->record($locked, $actor, RequestEventType::ReceiptAccepted, null, null, null, visit: $current);

                return;
            }

            $to = $current->step()->firstOrFail()->task === RouteTask::FinalCheck ? RequestStatus::ForFinalApproval : RequestStatus::InProgress;

            $locked->status === $to
                ? $this->record($locked, $actor, RequestEventType::ReceiptAccepted, null, null, null, visit: $current)
                : $this->transition($locked, $locked, $to, $actor, RequestEventType::ReceiptAccepted, visit: $current);
        });
    }

    /**
     * Approve / release to the next office (REQ-05-04, 05, 07).
     */
    public function completeVisit(OfficeVisit $visit, User $actor, ?string $remarks = null): void
    {
        DB::transaction(function () use ($visit, $actor, $remarks): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::Received]);

            $this->closeVisit($current, $actor, VisitStatus::Completed, ['remarks' => filled($remarks) ? $remarks : null]);
            $visit->setRawAttributes($current->getAttributes(), sync: true);

            $step = $current->step()->with('department')->firstOrFail();
            $office = $current->department()->firstOrFail();

            if ($current->purpose === VisitPurpose::Correction) {
                // Corrected: back to the office that returned it, which must receive it again.
                $this->record($locked, $actor, RequestEventType::DocumentResubmitted, null, null, $remarks, visit: $current);
                $this->forward($locked, $step, $step->department, VisitPurpose::Resubmission, $office, $actor);

                return;
            }

            $step->forceFill(['status' => StepStatus::Completed, 'completed_at' => now()])->save();
            $this->record($locked, $actor, RequestEventType::StepCompleted, null, null, $remarks, $step, $current);
            $this->advance($locked, $step, $office, $actor);
        });
    }

    /**
     * Return the document to an earlier office for correction (REQ-05-06).
     */
    public function returnVisit(OfficeVisit $visit, User $actor, Department $to, string $reason, string $correctiveAction, ?string $remarks = null): void
    {
        if (blank($reason) || blank($correctiveAction)) {
            throw new InvalidTransition('Give the reason for the return and the corrective action needed.');
        }

        DB::transaction(function () use ($visit, $actor, $to, $reason, $correctiveAction, $remarks): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::Received]);

            if ($current->purpose === VisitPurpose::Correction) {
                throw new InvalidTransition('Send the corrected document back instead of returning it again.');
            }

            $step = $current->step()->firstOrFail();
            $earlier = $locked->steps()->where('position', '<', $step->position)->where('status', StepStatus::Completed)->pluck('department_id');

            if (! $earlier->contains($to->id)) {
                throw new InvalidTransition('A document can only be returned to an office it has already passed through.');
            }

            $this->closeVisit($current, $actor, VisitStatus::Returned, [
                'returned_to_department_id' => $to->id,
                'return_reason' => $reason,
                'corrective_action' => $correctiveAction,
                'remarks' => filled($remarks) ? $remarks : null,
            ]);
            $visit->setRawAttributes($current->getAttributes(), sync: true);

            $office = $current->department()->firstOrFail();
            $this->transition($locked, $locked, RequestStatus::ReturnedForCorrection, $actor, RequestEventType::DocumentReturned,
                "Returned to {$to->name}: {$reason}\nCorrective action: {$correctiveAction}", visit: $current);
            $this->forward($locked, $step, $to, VisitPurpose::Correction, $office, $actor);
        });
    }

    /**
     * Record why a request is delayed without changing its status (REQ-05-09).
     */
    public function recordDelay(CredentialRequest $request, User $actor, DelayCategory $category, string $reason, ?string $correctiveAction = null): void
    {
        if (blank($reason)) {
            throw new InvalidTransition('Explain the reason for the delay.');
        }

        DB::transaction(function () use ($request, $actor, $category, $reason, $correctiveAction): void {
            $locked = $this->lock($request, RequestStatus::open(), 'This request is already closed.');
            $visit = $locked->openVisit()->first();

            $locked->delayNotes()->create([
                'office_visit_id' => $visit?->id,
                'department_id' => $visit?->department_id,
                'category' => $category,
                'reason' => $reason,
                'corrective_action' => filled($correctiveAction) ? $correctiveAction : null,
                'recorded_by' => $actor->id,
            ]);

            $this->record($locked, $actor, RequestEventType::DelayRecorded, null, null,
                "{$category->label()}: {$reason}".(filled($correctiveAction) ? "\nCorrective action: {$correctiveAction}" : ''), visit: $visit);
        });
    }

    /**
     * Registrar override: pass over the current office (REQ-05-10).
     */
    public function bypassStep(CredentialRequest $request, User $registrar, string $reason): void
    {
        if (blank($reason)) {
            throw new InvalidTransition('A reason is required to bypass a step.');
        }

        DB::transaction(function () use ($request, $registrar, $reason): void {
            $locked = $this->lock($request, RequestStatus::processing(), 'This request is not being processed.');
            /** @var ?OfficeVisit $visit */
            $visit = $locked->openVisit()->lockForUpdate()->first();
            $step = $visit?->step()->with('department')->first();

            if ($visit === null || $step === null || $visit->purpose !== VisitPurpose::Route || $step->task === RouteTask::FinalCheck) {
                throw new InvalidTransition('Only an office step on the route (not a correction or the final check) can be bypassed.');
            }

            $this->closeVisit($visit, $registrar, VisitStatus::Bypassed, ['remarks' => $reason]);
            $step->forceFill(['status' => StepStatus::Bypassed, 'completed_at' => now()])->save();

            $this->record($locked, $registrar, RequestEventType::StepBypassed, null, null, $reason, $step, $visit);
            $this->advance($locked, $step, $step->department, $registrar);
            $request->setRawAttributes($locked->getAttributes(), sync: true);
        });
    }

    // ── Representatives and release (spec 11) ──────────────────────────────────────────────

    /**
     * @param  array{full_name: string, relationship?: ?string, contact_number?: ?string}  $data
     */
    public function declareRepresentative(CredentialRequest $request, User $actor, array $data, ?UploadedFile $spa = null, ?UploadedFile $idFile = null): RequestRepresentative
    {
        $files = array_filter([DocumentKind::Spa->value => $spa, DocumentKind::RepresentativeId->value => $idFile]);
        $stored = [];

        foreach ($files as $kind => $file) {
            $stored[$kind] = $this->store($file, "request-documents/{$request->id}");
        }

        try {
            return DB::transaction(function () use ($request, $actor, $data, $files, $stored): RequestRepresentative {
                $locked = $this->lock($request, RequestStatus::open(), 'This request is already closed.');

                $representative = $locked->representatives()->make([
                    'full_name' => $data['full_name'],
                    'relationship' => $data['relationship'] ?? null,
                    'contact_number' => $data['contact_number'] ?? null,
                ]);
                $representative->forceFill(['status' => ReviewStatus::Pending, 'declared_by' => $actor->id])->save();

                foreach ($files as $kind => $file) {
                    $locked->documents()->create([
                        'request_representative_id' => $representative->id,
                        'kind' => $kind,
                        'path' => $stored[$kind],
                        'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                        'mime_type' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                        'uploaded_by' => $actor->id,
                    ]);
                }

                $locked->requester_mode = RequesterMode::Representative;
                $locked->save();
                $request->setRawAttributes($locked->getAttributes(), sync: true);

                $relationship = filled($data['relationship'] ?? null) ? " ({$data['relationship']})" : '';
                $this->record($locked, $actor, RequestEventType::RepresentativeDeclared, null, null, $data['full_name'].$relationship);

                return $representative;
            });
        } catch (Throwable $e) {
            Storage::disk(self::proofDisk())->delete(array_values($stored));

            throw $e;
        }
    }

    public function reviewRepresentative(RequestRepresentative $representative, User $registrar, bool $verified, ?string $remarks = null): void
    {
        if (! $verified && blank($remarks)) {
            throw new InvalidTransition('Give the reason for not accepting the representative.');
        }

        DB::transaction(function () use ($representative, $registrar, $verified, $remarks): void {
            $locked = $this->lock($representative->credentialRequest()->firstOrFail(), RequestStatus::open(), 'This request is already closed.');

            /** @var RequestRepresentative $current */
            $current = RequestRepresentative::whereKey($representative->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== ReviewStatus::Pending) {
                throw new InvalidTransition('This representative has already been reviewed.');
            }

            $current->forceFill([
                'status' => $verified ? ReviewStatus::Verified : ReviewStatus::Rejected,
                'reviewed_by' => $registrar->id,
                'reviewed_at' => now(),
                'remarks' => filled($remarks) ? $remarks : null,
            ])->save();
            $representative->setRawAttributes($current->getAttributes(), sync: true);

            $this->record($locked, $registrar, $verified ? RequestEventType::RepresentativeVerified : RequestEventType::RepresentativeRejected,
                null, null, $current->full_name.(filled($remarks) ? ": {$remarks}" : ''));
        });
    }

    /**
     * Hand the credential over and record who received it (REQ-11-06..08).
     *
     * @param  array{recipient_type: RecipientType, representative_id?: ?int, recipient_name: string, id_presented: string, identity_checked: bool, acknowledged: bool, remarks?: ?string}  $claim
     */
    public function release(CredentialRequest $request, User $registrar, array $claim): void
    {
        if (blank($claim['recipient_name']) || blank($claim['id_presented'])) {
            throw new InvalidTransition("Record the recipient's name and the ID they presented.");
        }

        if (! $claim['identity_checked'] || ! $claim['acknowledged']) {
            throw new InvalidTransition("Confirm the recipient's identity and their acknowledgment of receipt before releasing.");
        }

        DB::transaction(function () use ($request, $registrar, $claim): void {
            $locked = $this->lock($request, [RequestStatus::ReadyForRelease], 'Only credentials that are ready for release can be released.');
            $representative = null;

            if ($claim['recipient_type'] === RecipientType::Representative) {
                $representative = $locked->representatives()->whereKey($claim['representative_id'] ?? 0)->first();

                if ($representative === null || $representative->status !== ReviewStatus::Verified) {
                    throw new InvalidTransition("The representative's SPA and IDs must be verified before the credential is released to them.");
                }
            }

            $locked->release()->create([
                'recipient_type' => $claim['recipient_type'],
                'request_representative_id' => $representative?->id,
                'recipient_name' => $claim['recipient_name'],
                'id_presented' => $claim['id_presented'],
                'acknowledged' => true,
                'released_by' => $registrar->id,
                'released_at' => now(),
                'remarks' => filled($claim['remarks'] ?? null) ? $claim['remarks'] : null,
            ]);

            $locked->released_at = now();
            $locked->processing_working_days = $locked->accepted_at !== null
                ? $this->calendar->workingDaysBetween($locked->accepted_at, now())
                : null;

            $this->transition($locked, $request, RequestStatus::Released, $registrar, RequestEventType::Released,
                "Received by {$claim['recipient_name']} ({$claim['recipient_type']->label()})");
        });
    }

    // ── Internals ──────────────────────────────────────────────────────────────────────────

    /**
     * Start the processing clock: due date, route snapshot with the final check, first hand-off
     * (REQ-04-14, REQ-05-01).
     */
    private function accept(CredentialRequest $locked, CredentialRequest $original, User $registrar, ?string $remarks = null): void
    {
        $type = $locked->credentialType()->firstOrFail();
        $template = $type->steps()->get();

        if ($template->isEmpty()) {
            throw new InvalidTransition('This credential type has no route. Add its offices before accepting requests.');
        }

        $registrarOffice = Department::where('code', Department::REGISTRAR_CODE)->first()
            ?? throw new InvalidTransition('The Office of the Registrar (code REG) is missing.');

        $route = $template->map(fn ($step): array => [
            'department_id' => $step->is_student_college ? $locked->college_id : $step->department_id,
            'task' => $step->task,
            'instructions' => $step->instructions,
        ])->push([
            'department_id' => $registrarOffice->id,
            'task' => RouteTask::FinalCheck,
            'instructions' => 'Confirm the completeness and accuracy of the credential before marking it ready for release.',
        ]);

        foreach ($route->values() as $index => $step) {
            $locked->steps()->create($step + [
                'position' => $index + 1,
                'status' => $index === 0 ? StepStatus::Current : StepStatus::Waiting,
            ]);
        }

        $locked->forceFill([
            'accepted_at' => now(),
            'accepted_by' => $registrar->id,
            'due_at' => $this->calendar->addWorkingDays(now(), $type->processing_days),
        ]);
        $this->transition($locked, $original, RequestStatus::AcceptedForProcessing, $registrar, RequestEventType::Accepted, $remarks);

        $first = $locked->steps()->with('department')->firstOrFail();
        $this->forward($locked, $first, $first->department, VisitPurpose::Route, null, $registrar);

        // effective_due_at is computed by the database.
        $original->setRawAttributes(CredentialRequest::findOrFail($locked->id)->getAttributes(), sync: true);
    }

    /**
     * After a step is completed or bypassed: forward to the next step, or ready for release.
     */
    private function advance(CredentialRequest $locked, RequestStep $step, Department $from, User $actor): void
    {
        $next = $locked->steps()->with('department')
            ->where('position', '>', $step->position)
            ->where('status', StepStatus::Waiting)
            ->first();

        if ($next !== null) {
            $next->update(['status' => StepStatus::Current]);
            $this->forward($locked, $next, $next->department, VisitPurpose::Route, $from, $actor);

            return;
        }

        $locked->ready_at = now();
        $this->transition($locked, $locked, RequestStatus::ReadyForRelease, $actor, RequestEventType::ReadyForRelease);
    }

    /**
     * Dispatch the document to an office; it still has to be received there (REQ-05-03).
     */
    private function forward(CredentialRequest $locked, RequestStep $step, Department $to, VisitPurpose $purpose, ?Department $from, User $actor): OfficeVisit
    {
        $visit = new OfficeVisit;
        $visit->forceFill([
            'credential_request_id' => $locked->id,
            'request_step_id' => $step->id,
            'department_id' => $to->id,
            'visit_no' => $locked->visits()->where('department_id', $to->id)->count() + 1,
            'purpose' => $purpose,
            'status' => VisitStatus::InTransit,
            'from_department_id' => $from?->id,
            'forwarded_at' => now(),
            'forwarded_by' => $actor->id,
        ])->save();

        $this->record($locked, $actor, RequestEventType::DocumentForwarded, null, null, null, $step, $visit);

        return $visit;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function closeVisit(OfficeVisit $visit, User $actor, VisitStatus $status, array $attributes = []): void
    {
        $visit->forceFill($attributes + [
            'status' => $status,
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'working_days' => $visit->received_at !== null ? $this->calendar->workingDaysBetween($visit->received_at, now()) : null,
        ])->save();
    }

    private function closeOpenVisit(CredentialRequest $locked, User $actor): void
    {
        $visit = $locked->openVisit()->first();

        if ($visit !== null) {
            $this->closeVisit($visit, $actor, VisitStatus::Bypassed);
        }
    }

    /**
     * Lock the visit's request (being processed) and re-read the visit, asserting it is in one
     * of the given statuses.
     *
     * @param  list<VisitStatus>  $allowed
     * @return array{CredentialRequest, OfficeVisit}
     */
    private function lockVisit(OfficeVisit $visit, array $allowed): array
    {
        $locked = $this->lock(
            $visit->credentialRequest()->firstOrFail(),
            RequestStatus::processing(),
            'This request is not being processed.',
        );

        /** @var OfficeVisit $current */
        $current = OfficeVisit::whereKey($visit->getKey())->lockForUpdate()->firstOrFail();

        if (! in_array($current->status, $allowed, true)) {
            throw new InvalidTransition($current->status->isOpen()
                ? 'This action is not available for the document right now.'
                : 'This office visit has already been completed.');
        }

        return [$locked, $current];
    }

    /**
     * Re-read the request with a row lock and assert its status.
     *
     * @param  list<RequestStatus>  $allowed
     */
    private function lock(CredentialRequest $request, array $allowed, string $message): CredentialRequest
    {
        /** @var CredentialRequest $locked */
        $locked = CredentialRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

        if (! in_array($locked->status, $allowed, true)) {
            throw new InvalidTransition($message);
        }

        return $locked;
    }

    /**
     * Save a status change on the locked row, mirror it on the caller's instance and log it.
     */
    private function transition(
        CredentialRequest $locked,
        CredentialRequest $original,
        RequestStatus $to,
        ?User $actor,
        RequestEventType $type,
        ?string $remarks = null,
        ?RequestStep $step = null,
        ?OfficeVisit $visit = null,
    ): RequestEvent {
        $from = $locked->status;
        $locked->status = $to;
        $locked->save();

        $original->setRawAttributes($locked->getAttributes(), sync: true);

        return $this->record($locked, $actor, $type, $from, $to, $remarks, $step, $visit);
    }

    private function record(
        CredentialRequest $request,
        ?User $actor,
        RequestEventType $type,
        ?RequestStatus $from,
        ?RequestStatus $to,
        ?string $remarks = null,
        ?RequestStep $step = null,
        ?OfficeVisit $visit = null,
    ): RequestEvent {
        $event = $request->events()->create([
            'actor_id' => $actor?->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'request_step_id' => $step->id ?? $visit?->request_step_id,
            'office_visit_id' => $visit?->id,
            'department_id' => $visit->department_id ?? $step?->department_id,
            'remarks' => filled($remarks) ? $remarks : null,
        ]);

        DB::afterCommit(fn () => RequestEventRecorded::dispatch($event));

        return $event;
    }

    private function reviewLatestProof(CredentialRequest $request, User $registrar, PaymentStatus $status, ?string $officialReceiptNo = null, ?string $remarks = null): void
    {
        $proof = $request->paymentProofs()->where('status', PaymentStatus::Submitted)->first();

        if ($proof === null) {
            throw new InvalidTransition('There is no proof of payment waiting for verification.');
        }

        $proof->forceFill([
            'status' => $status,
            'official_receipt_no' => $officialReceiptNo,
            'reviewed_by' => $registrar->id,
            'reviewed_at' => now(),
            'remarks' => filled($remarks) ? $remarks : null,
        ])->save();
    }

    private function store(UploadedFile $file, string $folder): string
    {
        $path = $file->storeAs($folder, Str::uuid().'.'.$file->extension(), self::proofDisk());

        if ($path === false) {
            throw new InvalidTransition('The file could not be saved. Please try again.');
        }

        return $path;
    }
}
