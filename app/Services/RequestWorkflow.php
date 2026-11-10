<?php

namespace App\Services;

use App\Actions\Audit\RecordRequestEvent;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\DelayCategory;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequesterMode;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\VisitPurpose;
use App\Enums\VisitStatus;
use App\Events\RequestEventRecorded;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\OfficeVisit;
use App\Models\PaymentProof;
use App\Models\RequestCheck;
use App\Models\RequestDeficiency;
use App\Models\RequestDocument;
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
     * Submit a request with a copy of the owner's details and the ID sent with it, by default their
     * saved ID (REQ-04-03, REQ-15-06).
     *
     * @param  array{credential_type_id: int, purpose: string, receiving_institution?: ?string}  $data
     */
    public function submit(User $owner, array $data, ?IdentityDocument $identity = null): CredentialRequest
    {
        if (! $owner->hasRequesterDetails()) {
            throw new InvalidTransition('Give your name, contact number and academic information before requesting a credential.');
        }

        $identity ??= $owner->latestIdentityDocument()->first();

        if ($identity === null || $identity->user_id !== $owner->id) {
            throw new InvalidTransition('Upload a valid ID before requesting a credential.');
        }

        return DB::transaction(function () use ($owner, $data, $identity): CredentialRequest {
            $type = CredentialType::requestable()->findOrFail($data['credential_type_id']);
            $receivingInstitution = $data['receiving_institution'] ?? null;

            if ($type->requires_receiving_institution && blank($receivingInstitution)) {
                throw new InvalidTransition('Give the complete name of the receiving school.');
            }

            $request = new CredentialRequest([
                'credential_type_id' => $type->id,
                'purpose' => $data['purpose'],
                'receiving_institution' => $type->requires_receiving_institution ? $receivingInstitution : null,
            ]);

            $request->forceFill([
                'user_id' => $owner->id,
                'requester_name' => $owner->requesterName(),
                'contact_number' => $owner->contact_number,
                'identity_document_id' => $identity->id,
                'identity_status' => ReviewStatus::Pending,
                'college_id' => $owner->college_id,
                'academic_status' => $owner->academic_status,
                'program' => $owner->program,
                'student_number' => $owner->student_number,
                'attended_from' => $owner->attended_from,
                'attended_to' => $owner->attended_to,
                'requester_mode' => RequesterMode::Personal,
                'requires_payment' => $type->requires_payment,
                'fee' => $type->requires_payment ? $type->fee : null,
                // One request is one document, so the amount due is the fee (REQ-04-03).
                'amount_due' => $type->requires_payment ? $type->fee : null,
                'status' => RequestStatus::Submitted,
                'submitted_at' => now(),
            ])->save();

            // The tracking number uses the id, so it is set once the row exists (REQ-04-03).
            $request->forceFill([
                'reference_no' => CredentialRequest::trackingNumber($request->id, $request->created_at),
            ])->save();

            $this->record($request, $owner, RequestEventType::Submitted, null, $request->status);

            return $request;
        });
    }

    // ── Initial Evaluation (specs 04, 16) ──────────────────────────────────────────────────

    /**
     * Take a submitted request up for evaluation; the registrar becomes its evaluator, who alone
     * may act on it until the decision (REQ-04-05, REQ-16-09). The first start is kept for the
     * initial evaluation time.
     */
    public function startEvaluation(CredentialRequest $request, User $registrar): void
    {
        DB::transaction(function () use ($request, $registrar): void {
            $locked = $this->lock($request, [RequestStatus::Submitted], 'Only submitted requests can be taken up for evaluation.');

            $locked->forceFill(['evaluation_started_at' => $locked->evaluation_started_at ?? now(), 'evaluated_by' => $registrar->id]);
            $this->transition($locked, $request, RequestStatus::UnderEvaluation, $registrar, RequestEventType::EvaluationStarted);
        });
    }

    /**
     * Confirm or reject one part of a stage's checklist (REQ-16-10, 17, 19, 22).
     */
    public function recordCheck(CredentialRequest $request, User $registrar, CheckItem $item, CheckResult $result): void
    {
        DB::transaction(function () use ($request, $registrar, $item, $result): void {
            $locked = $this->lock($request, [self::STAGE_STATUS[$item->stage()->value]], 'This part cannot be checked at the request\'s current stage.');
            $this->assertEvaluator($locked, $registrar);

            if (! in_array($item, CheckItem::for($item->stage(), $locked), true)) {
                throw new InvalidTransition('This part does not apply to this request.');
            }

            RequestCheck::query()->updateOrInsert(
                ['credential_request_id' => $locked->id, 'item' => $item->value],
                ['result' => $result->value, 'checked_by' => $registrar->id, 'checked_at' => now()],
            );
        });
    }

    /**
     * Every part confirmed: the ID and representatives are verified, then payment instructions for
     * a paid request or acceptance for a free one (REQ-16-12, REQ-04-08).
     */
    public function approveEvaluation(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::UnderEvaluation], 'Only a request under evaluation can be approved.');
            $this->assertEvaluator($locked, $registrar);
            $this->assertAllConfirmed($locked, CheckStage::Evaluation);

            $this->completeRequirements($locked, $request, $registrar, $remarks);
        });
    }

    /**
     * Back to the requester to correct the request online and resubmit it, with what was rejected
     * (REQ-20-03). A rejected ID is replaced and a rejected representative replaced by another.
     */
    public function returnForClarification(CredentialRequest $request, User $registrar, string $remarks): void
    {
        if (blank($remarks)) {
            throw new InvalidTransition('Tell the requester what to correct.');
        }

        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::UnderEvaluation], 'Only a request under evaluation can be returned for clarification.');
            $this->assertEvaluator($locked, $registrar);

            $results = $this->results($locked, CheckStage::Evaluation);
            $rejected = array_values(array_filter(
                CheckItem::for(CheckStage::Evaluation, $locked),
                fn (CheckItem $item): bool => ($results[$item->value] ?? null) === CheckResult::Rejected,
            ));

            if ($rejected === []) {
                throw new InvalidTransition('Reject at least one part so the requester knows what to fix.');
            }

            if (in_array(CheckItem::Identity, $rejected, true)) {
                $locked->forceFill([
                    'identity_status' => ReviewStatus::Rejected,
                    'identity_reviewed_by' => $registrar->id,
                    'identity_reviewed_at' => now(),
                    'identity_remarks' => $remarks,
                ]);
            }

            if (in_array(CheckItem::Representative, $rejected, true)) {
                $this->reviewPendingRepresentatives($locked, $registrar, ReviewStatus::Rejected, $remarks);
            }

            // The lock ends with the status (REQ-16-09); the evaluator stays recorded for the reports.
            $this->transition($locked, $request, RequestStatus::ReturnedForClarification, $registrar, RequestEventType::CustomerActionRequested,
                self::rejectedSummary($rejected, $remarks));
        });
    }

    /**
     * Originals or clearance are missing: the requester complies at the office, and the request
     * waits for the registrar to record it (REQ-20-04, Proposed Workflow III-C).
     *
     * @param  list<string>  $missing  each missing requirement, as the requester will read it
     */
    public function requireOfficeVisit(CredentialRequest $request, User $registrar, array $missing, string $remarks): void
    {
        $missing = array_values(array_unique(array_filter(array_map(trim(...), $missing), filled(...))));

        if ($missing === []) {
            throw new InvalidTransition('Tick at least one missing requirement, so the requester knows what to bring.');
        }

        if (blank($remarks)) {
            throw new InvalidTransition('Tell the requester what to bring.');
        }

        DB::transaction(function () use ($request, $registrar, $missing, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::UnderEvaluation], 'Only a request under evaluation can require an office visit.');
            $this->assertEvaluator($locked, $registrar);

            foreach ($missing as $item) {
                $locked->deficiencies()->make(['description' => Str::limit($item, 250, '')])
                    ->forceFill(['recorded_by' => $registrar->id])
                    ->save();
            }

            $this->transition($locked, $request, RequestStatus::PendingCompliance, $registrar, RequestEventType::ComplianceRequested,
                'Missing: '.implode('; ', $missing)."\n{$remarks}");
        });
    }

    /**
     * One missing item was submitted at the office: when, and who verified it (REQ-20-09).
     */
    public function markReceived(RequestDeficiency $deficiency, User $registrar): void
    {
        DB::transaction(function () use ($deficiency, $registrar): void {
            $locked = $this->lock($deficiency->credentialRequest()->firstOrFail(), [RequestStatus::PendingCompliance], 'Only a request pending compliance takes missing documents.');

            /** @var RequestDeficiency $current */
            $current = RequestDeficiency::whereKey($deficiency->id)->lockForUpdate()->firstOrFail();

            if ($current->resolved_at !== null) {
                throw new InvalidTransition('This was already received.');
            }

            $current->forceFill(['resolved_at' => now(), 'resolved_by' => $registrar->id])->save();
            $deficiency->setRawAttributes($current->getAttributes(), sync: true);

            $this->record($locked, $registrar, RequestEventType::DeficiencyResolved, null, null, $current->description);
        });
    }

    /**
     * Everything missing was received: the evaluation is complete, so payment instructions follow,
     * or a free request is accepted (REQ-20-09).
     */
    public function confirmCompliance(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PendingCompliance], 'Only a request pending compliance can be confirmed.');

            if ($locked->deficiencies()->whereNull('resolved_at')->exists()) {
                throw new InvalidTransition('Mark every missing requirement received first.');
            }

            foreach (CheckItem::for(CheckStage::Evaluation, $locked) as $item) {
                RequestCheck::query()->updateOrInsert(
                    ['credential_request_id' => $locked->id, 'item' => $item->value],
                    ['result' => CheckResult::Confirmed->value, 'checked_by' => $registrar->id, 'checked_at' => now()],
                );
            }

            $this->completeRequirements($locked, $request, $registrar, $remarks);
        });
    }

    /**
     * The requester corrected what was returned and sends it back to the Initial Evaluation queue
     * (REQ-20-07). With changes, the request takes the new credential, purpose and receiving school,
     * its fee, and a new copy of the owner's details; a new ID awaits verification. Rejected parts
     * and the parts whose data changed are checked again.
     *
     * @param  ?array{credential_type_id: int, purpose: string, receiving_institution?: ?string}  $changes
     */
    public function resubmit(CredentialRequest $request, User $owner, ?string $note = null, ?array $changes = null, ?IdentityDocument $identity = null, bool $newRequirementCopies = false): void
    {
        if ($request->user_id !== $owner->id) {
            throw new InvalidTransition('Only the requester can resubmit this request.');
        }

        if ($identity !== null && $identity->user_id !== $owner->id) {
            throw new InvalidTransition('Upload your own valid ID.');
        }

        DB::transaction(function () use ($request, $owner, $note, $changes, $identity, $newRequirementCopies): void {
            $locked = $this->lock($request, [RequestStatus::ReturnedForClarification], 'Only a request returned to you for clarification can be resubmitted.');

            if ($locked->identity_status === ReviewStatus::Rejected && $identity === null) {
                throw new InvalidTransition('Upload another valid ID: the one sent with this request was not accepted.');
            }

            $recheck = [];

            if ($changes !== null) {
                $recheck = $this->applyChanges($locked, $owner, $changes);
            }

            if ($newRequirementCopies) {
                $recheck[] = CheckItem::AcademicRecord;
            }

            if ($identity !== null) {
                $locked->forceFill([
                    'identity_document_id' => $identity->id,
                    'identity_status' => ReviewStatus::Pending,
                    'identity_reviewed_by' => null,
                    'identity_reviewed_at' => null,
                    'identity_remarks' => null,
                ]);
                $recheck[] = CheckItem::Identity;
            }

            $this->clearRejected($locked, CheckStage::Evaluation);
            $locked->checks()->whereIn('item', array_map(fn (CheckItem $item) => $item->value, $recheck))->delete();

            if ($identity !== null) {
                $locked->save();
                $this->record($locked, $owner, RequestEventType::IdentityResubmitted, null, null, $identity->id_type);
            }

            $this->transition($locked, $request, RequestStatus::Submitted, $owner, RequestEventType::Resubmitted, filled($note) ? $note : null);
        });
    }

    /**
     * The stage's decision to end the request: at the evaluation, the payment validation or the
     * final quality check (REQ-16-14, 17, 19). At the final check the document's visit is closed.
     */
    public function decline(CredentialRequest $request, User $registrar, string $reason): void
    {
        if (blank($reason)) {
            throw new InvalidTransition('A reason is required to decline a request.');
        }

        DB::transaction(function () use ($request, $registrar, $reason): void {
            $locked = $this->lock(
                $request,
                [RequestStatus::UnderEvaluation, RequestStatus::PendingCompliance, RequestStatus::PaymentVerification, RequestStatus::ForFinalApproval],
                'A request can only be declined at its evaluation, office compliance, payment validation or final quality check.',
            );
            $this->assertEvaluator($locked, $registrar);

            if ($locked->status === RequestStatus::PaymentVerification) {
                $this->reviewLatestProof($locked, $registrar, PaymentStatus::Rejected, remarks: $reason);
            }

            $this->closeOpenVisit($locked, $registrar);
            $locked->forceFill(['declined_at' => now(), 'decline_reason' => $reason]);
            $this->transition($locked, $request, RequestStatus::Declined, $registrar, RequestEventType::Declined, $reason);
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
            $locked->checks()->whereIn('item', array_map(fn (CheckItem $item) => $item->value, CheckItem::for(CheckStage::Payment, $locked)))->delete();   // the next proof is checked anew
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
            $this->assertAllConfirmed($locked, CheckStage::Payment);

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Verified, $officialReceiptNo, $remarks);
            $this->record($locked, $registrar, RequestEventType::PaymentVerified, null, null, "Official receipt {$officialReceiptNo}");
            $this->accept($locked, $request, $registrar, $remarks);
        });
    }

    /**
     * Only the owner cancels, and only before acceptance (REQ-04-16, REQ-16-21).
     */
    public function cancel(CredentialRequest $request, User $actor, ?string $reason = null): void
    {
        if ($request->user_id !== $actor->id) {
            throw new InvalidTransition('Only the requester can cancel a request.');
        }

        DB::transaction(function () use ($request, $actor, $reason): void {
            $locked = $this->lock($request, RequestStatus::open(), 'This request is already closed.');

            if ($locked->accepted_at !== null || ! in_array($locked->status, [...RequestStatus::beforeAcceptance(), RequestStatus::ReturnedForCustomerAction], true)) {
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

            $this->receive($locked, $current, $actor);
            $visit->setRawAttributes($current->getAttributes(), sync: true);
        });
    }

    /**
     * The office finished its task: prepared, signed, stamped, checked or corrected (REQ-05-04,
     * REQ-17-06). The paper stays in the office until it is forwarded (forwardDocument), except
     * after the final quality check, which makes it ready for release (REQ-17-09), and when the
     * next office is the same office, since the paper does not move.
     */
    public function completeVisit(OfficeVisit $visit, User $actor, ?string $remarks = null): void
    {
        DB::transaction(function () use ($visit, $actor, $remarks): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::Received]);

            if ($this->markDone($locked, $current, $actor, $remarks)) {
                $this->leave($locked, $current, $actor);
            }

            $visit->setRawAttributes($current->getAttributes(), sync: true);
        });
    }

    /**
     * The registrar sends the paper on from an office whose task is done: to the next office on
     * the route, or a corrected document back to the office that returned it. That office still
     * has to accept its receipt (REQ-17-07, 08). The Registrar's own received task is marked done
     * in the same step (REQ-17-12). Only registrars forward, from In Progress (REQ-17-16).
     */
    public function forwardDocument(OfficeVisit $visit, User $actor): void
    {
        DB::transaction(function () use ($visit, $actor): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::Done, VisitStatus::Received]);

            if (! $current->load(['department', 'step'])->isForwardable()) {
                throw new InvalidTransition('The office has to finish its task before the document is forwarded.');
            }

            if ($current->status === VisitStatus::Received) {
                $this->markDone($locked, $current, $actor);
            }

            $this->leave($locked, $current, $actor);
            $visit->setRawAttributes($current->getAttributes(), sync: true);
        });
    }

    /**
     * Both final checks confirmed: complete the Registrar's final-check visit, which makes the
     * credential ready for release (REQ-16-19, REQ-05-05).
     */
    public function approveFinalCheck(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        if ($request->status !== RequestStatus::ForFinalApproval) {
            throw new InvalidTransition('Only a document received for the final quality check can be approved.');
        }

        $visit = $request->openVisit()->firstOrFail();
        $this->completeVisit($visit, $registrar, $remarks);
        $request->setRawAttributes($request->fresh()?->getAttributes() ?? $request->getAttributes(), sync: true);
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
     * The owner's copies of the credential's documentary requirements, sent with a new request
     * (REQ-04-18, 19): only for requirements the credential lists, at most
     * RequestDocument::MAX_PER_REQUIREMENT each. No ledger entry of its own: the files arrive with
     * the Submitted entry. Returns the rows so a caller whose own transaction fails can delete the
     * stored files.
     *
     * @param  array<string, list<UploadedFile>>  $files  requirement => its files
     * @return list<RequestDocument>
     */
    public function attachRequirementDocuments(CredentialRequest $request, User $owner, array $files): array
    {
        if ($request->user_id !== $owner->id) {
            throw new InvalidTransition('Only the requester can send copies of the requirements.');
        }

        $listed = $request->credentialType->requirements ?? [];

        foreach ($files as $requirement => $list) {
            if (! in_array($requirement, $listed, true)) {
                throw new InvalidTransition('This credential does not list that requirement.');
            }

            if (count($list) > RequestDocument::MAX_PER_REQUIREMENT) {
                throw new InvalidTransition('Upload at most '.RequestDocument::MAX_PER_REQUIREMENT.' files for each requirement.');
            }
        }

        /** @var list<array{string, UploadedFile, string}> $stored requirement, file, path */
        $stored = [];

        try {
            foreach ($files as $requirement => $list) {
                foreach ($list as $file) {
                    $stored[] = [$requirement, $file, $this->store($file, "request-documents/{$request->id}")];
                }
            }

            return DB::transaction(function () use ($request, $owner, $stored): array {
                $locked = $this->lock($request, [RequestStatus::Submitted], 'Copies of the requirements are sent with a new request only.');
                $documents = [];

                foreach ($stored as [$requirement, $file, $path]) {
                    $documents[] = $locked->documents()->create([
                        'kind' => DocumentKind::Requirement,
                        'requirement' => $requirement,
                        'path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                        'mime_type' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                        'uploaded_by' => $owner->id,
                    ]);
                }

                return $documents;
            });
        } catch (Throwable $e) {
            Storage::disk(self::proofDisk())->delete(array_column($stored, 2));

            throw $e;
        }
    }

    // ── Representatives and release (spec 11) ──────────────────────────────────────────────

    /**
     * A representative always comes with their SPA and valid ID (REQ-11-01).
     *
     * @param  array{full_name: string, relationship?: ?string, contact_number?: ?string}  $data
     */
    public function declareRepresentative(CredentialRequest $request, User $actor, array $data, UploadedFile $spa, UploadedFile $idFile): RequestRepresentative
    {
        $files = [DocumentKind::Spa->value => $spa, DocumentKind::RepresentativeId->value => $idFile];
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
     * Mark the credential released to the owner or a verified representative; the name comes from
     * them, the time and staff member are recorded (REQ-16-20, REQ-11-07, 08).
     */
    public function release(CredentialRequest $request, User $registrar, RecipientType $recipientType, ?int $representativeId = null, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $recipientType, $representativeId, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::ReadyForRelease], 'Only credentials that are ready for release can be released.');
            $representative = null;

            if ($recipientType === RecipientType::Representative) {
                $representative = $locked->representatives()->whereKey($representativeId ?? 0)->first();

                if ($representative === null || $representative->status !== ReviewStatus::Verified) {
                    throw new InvalidTransition("The representative's SPA and IDs must be verified before the credential is released to them.");
                }
            }

            $recipientName = $representative !== null ? $representative->full_name : $locked->requester_name;

            $locked->release()->create([
                'recipient_type' => $recipientType,
                'request_representative_id' => $representative?->id,
                'recipient_name' => $recipientName,
                'acknowledged' => true,
                'released_by' => $registrar->id,
                'released_at' => now(),
                'remarks' => filled($remarks) ? $remarks : null,
            ]);

            $locked->released_at = now();
            $locked->processing_working_days = $locked->accepted_at !== null
                ? $this->calendar->workingDaysBetween($locked->accepted_at, now())
                : null;

            $this->transition($locked, $request, RequestStatus::Released, $registrar, RequestEventType::Released,
                "Received by {$recipientName} ({$recipientType->label()})");
        });
    }

    // ── Internals ──────────────────────────────────────────────────────────────────────────

    /** The status in which each stage's checklist is filled in. */
    private const STAGE_STATUS = [
        'evaluation' => RequestStatus::UnderEvaluation,
        'payment' => RequestStatus::PaymentVerification,
        'final_check' => RequestStatus::ForFinalApproval,
    ];

    /**
     * While under evaluation, only its evaluator acts on the request (REQ-16-09).
     */
    private function assertEvaluator(CredentialRequest $locked, User $registrar): void
    {
        if ($locked->isEvaluatedBySomeoneElse($registrar)) {
            throw new InvalidTransition('This request is under evaluation by another evaluator.');
        }
    }

    /**
     * @return array<string, CheckResult>
     */
    private function results(CredentialRequest $locked, CheckStage $stage): array
    {
        return $locked->load('checks')->checkResults($stage);
    }

    private function assertAllConfirmed(CredentialRequest $locked, CheckStage $stage): void
    {
        $results = $this->results($locked, $stage);

        foreach (CheckItem::for($stage, $locked) as $item) {
            if (($results[$item->value] ?? null) !== CheckResult::Confirmed) {
                throw new InvalidTransition('Confirm every part of the checklist before approving.');
            }
        }
    }

    /** Rejected parts are checked again when the request comes back (REQ-20-07). */
    private function clearRejected(CredentialRequest $locked, CheckStage $stage): void
    {
        $locked->checks()
            ->whereIn('item', array_map(fn (CheckItem $item) => $item->value, CheckItem::for($stage, $locked)))
            ->where('result', CheckResult::Rejected)
            ->delete();
    }

    private function reviewPendingRepresentatives(CredentialRequest $locked, User $registrar, ReviewStatus $status, ?string $remarks = null): void
    {
        $locked->representatives()->where('status', ReviewStatus::Pending)->get()
            ->each(function (RequestRepresentative $representative) use ($registrar, $status, $remarks): void {
                $representative->forceFill([
                    'status' => $status,
                    'reviewed_by' => $registrar->id,
                    'reviewed_at' => now(),
                    'remarks' => $status === ReviewStatus::Rejected ? $remarks : null,
                ])->save();
            });
    }

    /**
     * The evaluation is complete: the ID and representatives are verified, then payment
     * instructions for a paid request or acceptance for a free one (REQ-16-12, REQ-20-09).
     */
    private function completeRequirements(CredentialRequest $locked, CredentialRequest $original, User $registrar, ?string $remarks): void
    {
        $locked->forceFill([
            'identity_status' => ReviewStatus::Verified,
            'identity_reviewed_by' => $registrar->id,
            'identity_reviewed_at' => now(),
            'identity_remarks' => null,
            'requirements_completed_at' => now(),
        ]);
        $this->reviewPendingRepresentatives($locked, $registrar, ReviewStatus::Verified);

        if ($locked->requires_payment) {
            $this->transition($locked, $original, RequestStatus::AwaitingPayment, $registrar, RequestEventType::RequirementsCompleted, $remarks);

            return;
        }

        $locked->save();
        $this->record($locked, $registrar, RequestEventType::RequirementsCompleted, null, null, $remarks);
        $this->accept($locked, $original, $registrar);
    }

    /**
     * The requester's corrections (REQ-20-07): the credential, purpose and receiving school, the fee
     * that follows, and a new copy of their details. Returns the checklist parts whose data changed.
     *
     * @param  array{credential_type_id: int, purpose: string, receiving_institution?: ?string}  $changes
     * @return list<CheckItem>
     */
    private function applyChanges(CredentialRequest $locked, User $owner, array $changes): array
    {
        if (! $owner->hasRequesterDetails()) {
            throw new InvalidTransition('Give your name, contact number and academic information before resubmitting.');
        }

        $type = CredentialType::requestable()->findOrFail($changes['credential_type_id']);
        $receivingInstitution = $changes['receiving_institution'] ?? null;

        if ($type->requires_receiving_institution && blank($receivingInstitution)) {
            throw new InvalidTransition('Give the complete name of the receiving school.');
        }

        $locked->fill([
            'credential_type_id' => $type->id,
            'purpose' => $changes['purpose'],
            // As on submission (REQ-04-03).
            'receiving_institution' => $type->requires_receiving_institution ? $receivingInstitution : null,
        ]);
        $locked->forceFill([
            'requester_name' => $owner->requesterName(),
            'contact_number' => $owner->contact_number,
            'college_id' => $owner->college_id,
            'academic_status' => $owner->academic_status,
            'program' => $owner->program,
            'student_number' => $owner->student_number,
            'attended_from' => $owner->attended_from,
            'attended_to' => $owner->attended_to,
            'requires_payment' => $type->requires_payment,
            'fee' => $type->requires_payment ? $type->fee : null,
            'amount_due' => $type->requires_payment ? $type->fee : null,
        ]);

        $changed = [];

        if ($locked->isDirty(['credential_type_id', 'purpose', 'receiving_institution'])) {
            $changed[] = CheckItem::CredentialRequest;
        }

        if ($locked->isDirty(['requester_name', 'contact_number'])) {
            $changed[] = CheckItem::Identity;
        }

        if ($locked->isDirty(['college_id', 'academic_status', 'program', 'student_number', 'attended_from', 'attended_to'])) {
            $changed[] = CheckItem::AcademicRecord;
        }

        if ($locked->isDirty('credential_type_id')) {
            $this->record($locked, $owner, RequestEventType::CredentialChanged, null, null,
                CredentialType::whereKey($locked->getOriginal('credential_type_id'))->value('name').' → '.$type->name);
        }

        $locked->save();

        return $changed;
    }

    /**
     * The ledger line of a return: what was rejected and the remarks (REQ-16-22).
     *
     * @param  list<CheckItem>  $rejected
     */
    private static function rejectedSummary(array $rejected, string $remarks): string
    {
        return 'Rejected: '.implode('; ', array_map(fn (CheckItem $item) => $item->label(), $rejected))."\n{$remarks}";
    }

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
     * The document is physically in the office: the receiving office, staff member and time are
     * recorded (REQ-05-03, REQ-17-08).
     */
    private function receive(CredentialRequest $locked, OfficeVisit $current, User $actor): void
    {
        $current->forceFill(['status' => VisitStatus::Received, 'received_at' => now(), 'received_by' => $actor->id])->save();

        if ($current->purpose === VisitPurpose::Correction) {
            $this->record($locked, $actor, RequestEventType::ReceiptAccepted, null, null, null, visit: $current);

            return;
        }

        // The first office's receipt keeps the request accepted for processing; it is in
        // progress once sent on (REQ-16-18). A corrected document received back resumes it.
        $to = match (true) {
            $current->step()->firstOrFail()->task === RouteTask::FinalCheck => RequestStatus::ForFinalApproval,
            $locked->status === RequestStatus::ReturnedForCorrection => RequestStatus::InProgress,
            default => $locked->status,
        };

        $locked->status === $to
            ? $this->record($locked, $actor, RequestEventType::ReceiptAccepted, null, null, null, visit: $current)
            : $this->transition($locked, $locked, $to, $actor, RequestEventType::ReceiptAccepted, visit: $current);
    }

    /**
     * Record the office's task done and keep the paper there (REQ-17-06). Returns whether the
     * paper moves on at once: after the final quality check, or when the next office is the same
     * office.
     */
    private function markDone(CredentialRequest $locked, OfficeVisit $current, User $actor, ?string $remarks = null): bool
    {
        $step = $current->step()->with('department')->firstOrFail();
        $correction = $current->purpose === VisitPurpose::Correction;

        if ($step->task === RouteTask::FinalCheck && ! $correction) {
            $this->assertAllConfirmed($locked, CheckStage::FinalCheck);   // REQ-16-19
        }

        $current->forceFill(['status' => VisitStatus::Done, 'done_at' => now(), 'done_by' => $actor->id, 'remarks' => filled($remarks) ? $remarks : null])->save();

        if ($correction) {
            $this->record($locked, $actor, RequestEventType::Corrected, null, null, $remarks, visit: $current);
        } else {
            $step->forceFill(['status' => StepStatus::Completed, 'completed_at' => now()])->save();
            $this->record($locked, $actor, RequestEventType::StepCompleted, null, null, $remarks, $step, $current);
        }

        $next = $current->setRelation('step', $step)->load('credentialRequest.steps')->nextStep();

        return $next === null || $next->department_id === $current->department_id;
    }

    /**
     * The paper leaves the office: the visit closes with the time it left and the working days
     * the office held it, and the document goes on (REQ-17-07, 09).
     */
    private function leave(CredentialRequest $locked, OfficeVisit $current, User $actor): void
    {
        $this->closeVisit($current, $actor, VisitStatus::Completed);

        $step = $current->step()->with('department')->firstOrFail();
        $office = $current->department()->firstOrFail();

        if ($current->purpose === VisitPurpose::Correction) {
            // Corrected: back to the office that returned it, which must receive it again.
            $this->record($locked, $actor, RequestEventType::DocumentResubmitted, null, null, null, visit: $current);
            $this->forward($locked, $step, $step->department, VisitPurpose::Resubmission, $office, $actor);

            return;
        }

        $this->advance($locked, $step, $office, $actor);
    }

    /**
     * Dispatch the document to an office; it still has to be received there (REQ-05-03). A paper
     * that does not move (accepted at the Registrar, or sent on within the same office) is
     * received at once (REQ-17-05).
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

        // Sent on from the first office: the paper is now moving between offices (REQ-16-18).
        $from !== null && $locked->status === RequestStatus::AcceptedForProcessing
            ? $this->transition($locked, $locked, RequestStatus::InProgress, $actor, RequestEventType::DocumentForwarded, null, $step, $visit)
            : $this->record($locked, $actor, RequestEventType::DocumentForwarded, null, null, null, $step, $visit);

        $stays = $from !== null ? $from->is($to) : $to->code === Department::REGISTRAR_CODE;

        if ($stays) {
            $this->receive($locked, $visit, $actor);
        }

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

        app(RecordRequestEvent::class)->handle($event, $request, $actor);   // REQ-14-01

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
