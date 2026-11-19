<?php

namespace App\Services;

use App\Actions\Audit\RecordRequestEvent;
use App\Enums\AcademicStatus;
use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\DocumentKind;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequesterMode;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\RouteTask;
use App\Enums\StepStatus;
use App\Enums\Term;
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
use App\Models\RequestApplication;
use App\Models\RequestCheck;
use App\Models\RequestDeficiency;
use App\Models\RequesterVerification;
use App\Models\RequestEvent;
use App\Models\RequestRepresentative;
use App\Models\RequestStep;
use App\Models\User;
use App\Support\VerifiedDetails;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single place where credential requests, their route steps and office visits change
 * status (specs 04, 05, 11).
 *
 * @phpstan-type CredentialItem array{credential_type_id: int, purpose: string, receiving_institution?: ?string, terms?: ?list<string>, academic_year?: ?string}
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
     * Submit an application for one or more credentials, with a copy of the owner's details and the
     * ID sent with it, by default their saved ID (REQ-04-03, REQ-15-06, REQ-27-01). Returns its
     * first credential, which carries it until acceptance (REQ-27-03).
     *
     * @param  CredentialItem|list<CredentialItem>  $data
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
            $request = $this->createApplication($data, $owner->id, RequestType::Online, [
                'requester_name' => $owner->requesterName(),
                'sex' => $owner->sex,
                'maiden_name' => $owner->maiden_name,
                'contact_number' => $owner->contact_number,
                'identity_document_id' => $identity->id,
                'identity_status' => ReviewStatus::Pending,
                'college_id' => $owner->college_id,
                'academic_status' => $owner->academic_status,
                'program' => $owner->program,
                'student_number' => $owner->student_number,
                'attended_from' => $owner->attended_from,
                'attended_to' => $owner->attended_to,
                'status' => RequestStatus::Submitted,
            ]);

            $this->record($request, $owner, RequestEventType::Submitted, null, $request->status);

            // Details verified on an earlier request are confirmed already (REQ-21-04).
            $this->carryVerifiedParts($request, $owner);
            $request->save();

            return $request;
        });
    }

    /**
     * The application and one credential row per item, each with the same copy of the requester's
     * details; returns the first (REQ-27-01, 03). The first carries the total amount due.
     *
     * @param  CredentialItem|list<CredentialItem>  $data
     * @param  array<string, mixed>  $snapshot  the requester's details and the starting status
     */
    private function createApplication(array $data, ?int $userId, RequestType $requestType, array $snapshot): CredentialRequest
    {
        $items = $this->items($data, self::status($snapshot['academic_status'] ?? null));
        $paid = $items->filter(fn (array $item): bool => $item['fee'] !== null);
        // Nothing to pay, e.g. a graduate's free Certificate of Grades, skips payment (REQ-29-07).
        $total = $paid->isNotEmpty() ? number_format($paid->sum(fn (array $item): float => (float) $item['fee']), 2, '.', '') : null;

        $application = RequestApplication::create([
            'reference_no' => 'tmp-'.Str::random(12),
            'user_id' => $userId,
            'request_type' => $requestType,
            'submitted_at' => now(),
        ]);
        // The tracking number uses the id, so it is set once the row exists (REQ-04-03).
        $application->update(['reference_no' => CredentialRequest::trackingNumber($application->id, $application->created_at)]);

        $requests = $items->map(function (array $item, int $index) use ($application, $userId, $requestType, $snapshot, $total): CredentialRequest {
            $type = $item['type'];
            $first = $index === 0;

            $request = new CredentialRequest([
                'credential_type_id' => $type->id,
                'purpose' => $item['purpose'],
                'receiving_institution' => $item['receiving_institution'],
            ]);

            $request->forceFill([
                ...$snapshot,
                'terms' => $item['terms'],
                'academic_year' => $item['academic_year'],
                'application_id' => $application->id,
                'item_no' => $index + 1,
                'reference_no' => $application->reference_no.'-'.($index + 1),
                'user_id' => $userId,
                'request_type' => $requestType,
                'requester_mode' => RequesterMode::Personal,
                // The application is paid once, on its first credential (REQ-27-03).
                'requires_payment' => $first ? $total !== null : $item['fee'] !== null,
                'fee' => $item['fee'],
                'amount_due' => $first ? $total : $item['fee'],
                'submitted_at' => now(),
            ])->save();

            return $request;
        });

        return $requests->first();
    }

    /**
     * The credentials asked for, each requestable, with the receiving school, terms and academic year
     * it needs and its fee for the requester's status, and none twice: a credential that asks for the
     * academic year may come once per year (REQ-27-01, REQ-29-04..07).
     *
     * @param  CredentialItem|list<CredentialItem>  $data
     * @return Collection<int, array{type: CredentialType, purpose: string, receiving_institution: ?string, terms: ?list<string>, academic_year: ?string, fee: ?string}>
     */
    private function items(array $data, ?AcademicStatus $status): Collection
    {
        $list = isset($data['credential_type_id']) ? [$data] : $data;

        if ($list === []) {
            throw new InvalidTransition('Choose at least one credential.');
        }

        $items = collect();

        foreach ($list as $item) {
            $type = CredentialType::requestable()->findOrFail((int) $item['credential_type_id']);
            $receivingInstitution = $item['receiving_institution'] ?? null;

            if ($type->requires_receiving_institution && blank($receivingInstitution)) {
                throw new InvalidTransition('Give the complete name of the receiving school.');
            }

            [$terms, $academicYear] = self::termsAndYear($type, $item);

            $items->push([
                'type' => $type,
                'purpose' => (string) $item['purpose'],
                'receiving_institution' => $type->requires_receiving_institution ? $receivingInstitution : null,
                'terms' => $terms,
                'academic_year' => $academicYear,
                'fee' => $type->feeFor($status, count($terms ?? [])),
            ]);
        }

        if ($items->map(fn (array $item): string => $item['type']->id.'|'.$item['academic_year'])->duplicates()->isNotEmpty()) {
            throw new InvalidTransition('Each credential can be requested only once in an application, or once per academic year.');
        }

        return $items;
    }

    /**
     * The terms and academic year of a credential that asks for them, checked; nothing for any other
     * (REQ-29-04).
     *
     * @param  array{terms?: ?list<string>, academic_year?: ?string}  $item
     * @return array{0: ?list<string>, 1: ?string}
     */
    private static function termsAndYear(CredentialType $type, array $item): array
    {
        if (! $type->asks_term_and_year) {
            return [null, null];
        }

        $terms = array_values(array_filter(
            array_map(fn (Term $term): string => $term->value, Term::cases()),
            fn (string $term): bool => in_array($term, $item['terms'] ?? [], true),
        ));
        $academicYear = $item['academic_year'] ?? null;

        if ($terms === [] || ! in_array($academicYear, CredentialType::academicYears(), true)) {
            throw new InvalidTransition('Choose the terms and the academic year.');
        }

        return [$terms, $academicYear];
    }

    private static function status(mixed $value): ?AcademicStatus
    {
        return $value instanceof AcademicStatus ? $value : AcademicStatus::tryFrom((string) $value);
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

            // The evaluator's own result replaces one carried from an earlier verification (REQ-21-05).
            RequestCheck::query()->updateOrInsert(
                ['credential_request_id' => $locked->id, 'item' => $item->value],
                ['result' => $result->value, 'checked_by' => $registrar->id, 'checked_at' => now(), 'carried_from_request_id' => null],
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

            // A rejected part is no longer verified, so the next request has it checked (REQ-21-03).
            $dropped = array_values(array_intersect(array_map(fn (CheckItem $item) => $item->value, $rejected), array_map(fn (CheckItem $item) => $item->value, VerifiedDetails::PARTS)));

            if ($dropped !== []) {
                RequesterVerification::query()->where('user_id', $locked->user_id)->whereIn('part', $dropped)->delete();
            }

            // The lock ends with the status (REQ-16-09); the evaluator stays recorded for the reports.
            $this->transition($locked, $request, RequestStatus::ReturnedForClarification, $registrar, RequestEventType::CustomerActionRequested,
                self::rejectedSummary($rejected, $remarks));
        });
    }

    /**
     * Originals or clearance are required at the office: the requester complies there, and the
     * request waits for the registrar to record it (REQ-20-04, Proposed Workflow III-C).
     *
     * @param  list<string>  $items  each required item, as the requester will read it
     */
    public function requireOfficeVisit(CredentialRequest $request, User $registrar, array $items, string $remarks): void
    {
        $items = array_values(array_unique(array_filter(array_map(trim(...), $items), filled(...))));

        if ($items === []) {
            throw new InvalidTransition('Tick at least one requirement, so the requester knows what to bring.');
        }

        DB::transaction(function () use ($request, $registrar, $items, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::UnderEvaluation], 'Only a request under evaluation can require an office visit.');
            $this->assertEvaluator($locked, $registrar);

            foreach ($items as $item) {
                $locked->deficiencies()->make(['description' => Str::limit($item, 250, '')])
                    ->forceFill(['recorded_by' => $registrar->id])
                    ->save();
            }

            $this->transition($locked, $request, RequestStatus::PendingCompliance, $registrar, RequestEventType::ComplianceRequested,
                // "Required", never "missing": the office needs the original. Remarks are optional.
                'Required: '.implode('; ', $items).(filled($remarks) ? "\n".trim($remarks) : ''));
        });
    }

    /**
     * One required item was submitted at the office: when, and who verified it (REQ-20-09).
     */
    public function markReceived(RequestDeficiency $deficiency, User $registrar): void
    {
        DB::transaction(function () use ($deficiency, $registrar): void {
            $locked = $this->lock($deficiency->credentialRequest()->firstOrFail(), [RequestStatus::PendingCompliance], 'Only a request pending compliance has items to receive.');

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
     * Every required item was received: the evaluation is complete, so payment instructions follow,
     * or a free request is accepted (REQ-20-09).
     */
    public function confirmCompliance(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PendingCompliance], 'Only a request pending compliance can be confirmed.');

            if ($locked->deficiencies()->whereNull('resolved_at')->exists()) {
                throw new InvalidTransition('Mark every requirement received first.');
            }

            foreach (CheckItem::for(CheckStage::Evaluation, $locked) as $item) {
                RequestCheck::query()->updateOrInsert(
                    ['credential_request_id' => $locked->id, 'item' => $item->value],
                    ['result' => CheckResult::Confirmed->value, 'checked_by' => $registrar->id, 'checked_at' => now(), 'carried_from_request_id' => null],
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
     * @param  ?CredentialItem  $changes
     */
    public function resubmit(CredentialRequest $request, User $owner, ?string $note = null, ?array $changes = null, ?IdentityDocument $identity = null): void
    {
        if ($request->user_id !== $owner->id) {
            throw new InvalidTransition('Only the requester can resubmit this request.');
        }

        if ($identity !== null && $identity->user_id !== $owner->id) {
            throw new InvalidTransition('Upload your own valid ID.');
        }

        DB::transaction(function () use ($request, $owner, $note, $changes, $identity): void {
            $locked = $this->lock($request, [RequestStatus::ReturnedForClarification], 'Only a request returned to you for clarification can be resubmitted.');

            if ($locked->identity_status === ReviewStatus::Rejected && $identity === null) {
                throw new InvalidTransition('Upload another valid ID: the one sent with this request was not accepted.');
            }

            $recheck = [];

            if ($changes !== null) {
                $recheck = $this->applyChanges($locked, $owner, $changes);
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

            // Parts checked again whose new copy was verified before are confirmed already (REQ-21-04).
            $this->carryVerifiedParts($locked, $owner);

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

            // An online proof under review is rejected with it; a walk-in payment has none (REQ-26-05).
            if ($locked->status === RequestStatus::PaymentVerification && ! $locked->isWalkIn()) {
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
        ?string $note = null,
    ): PaymentProof {
        $path = $this->store($file, "payment-proofs/{$request->id}");

        try {
            return DB::transaction(function () use ($request, $owner, $file, $path, $referenceNo, $amount, $paidOn, $note): PaymentProof {
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

                // The requester's note to the registrar, after a returned proof (REQ-04-20).
                $this->transition($locked, $request, RequestStatus::PaymentVerification, $owner, RequestEventType::PaymentSubmitted,
                    filled($note) ? $note : null);

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

            // Paid at the counter: validated or declined there, never returned (REQ-26-05).
            if ($locked->isWalkIn()) {
                throw new InvalidTransition('A walk-in payment cannot be returned. Validate it or decline the request.');
            }

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Rejected, remarks: $remarks);
            $this->transition($locked, $request, RequestStatus::AwaitingPayment, $registrar, RequestEventType::PaymentRejected, $remarks);
        });
    }

    /**
     * The registrar confirmed the payment against the official payment record: accept the request
     * for processing (REQ-04-13, 14). No checklist and no receipt number (REQ-16-17).
     */
    public function verifyPaymentAndAccept(CredentialRequest $request, User $registrar, ?string $remarks = null): void
    {
        DB::transaction(function () use ($request, $registrar, $remarks): void {
            $locked = $this->lock($request, [RequestStatus::PaymentVerification], 'Only payments awaiting verification can be verified.');

            $this->reviewLatestProof($locked, $registrar, PaymentStatus::Verified, remarks: $remarks);
            $this->record($locked, $registrar, RequestEventType::PaymentVerified, null, null);
            $this->accept($locked, $request, $registrar, $remarks);
        });
    }

    // ── Walk-in requests (spec 26) ─────────────────────────────────────────────────────────

    /**
     * Record a walk-in client's request: the registrar validated it at the counter, so it goes
     * straight to Payment Validation, or is accepted when the credential is free. It belongs to the
     * verified requester account with the client's email, if there is one (REQ-26-04, 08).
     *
     * @param  CredentialItem|list<CredentialItem>  $data
     * @param  array{first_name: string, middle_name: ?string, last_name: string, sex: string, maiden_name: ?string, email: string, contact_number: string, academic_status: string, college_id: int, program: string, student_number: ?string, attended_from: int, attended_to: int}  $details
     */
    public function submitWalkIn(User $registrar, array $data, array $details): CredentialRequest
    {
        return DB::transaction(function () use ($registrar, $data, $details): CredentialRequest {
            $email = Str::lower($details['email']);
            // Priced for the client's status, so a free credential needs no payment (REQ-29-07).
            $requiresPayment = $this->items($data, self::status($details['academic_status']))->contains(fn (array $item): bool => $item['fee'] !== null);

            $request = $this->createApplication($data, self::requesterAccount($email)?->id, RequestType::WalkIn, [
                'recorded_by' => $registrar->id,
                'requester_name' => trim(implode(' ', array_filter([$details['first_name'], $details['middle_name'], $details['last_name']], filled(...)))),
                'sex' => $details['sex'],
                'maiden_name' => $details['maiden_name'],
                'requester_email' => $email,
                'contact_number' => $details['contact_number'],
                'college_id' => $details['college_id'],
                'academic_status' => $details['academic_status'],
                'program' => $details['program'],
                'student_number' => $details['student_number'],
                'attended_from' => $details['attended_from'],
                'attended_to' => $details['attended_to'],
                // Seen in person and validated by the registrar who records it (REQ-26-03).
                'identity_status' => ReviewStatus::Verified,
                'identity_reviewed_by' => $registrar->id,
                'identity_reviewed_at' => now(),
                'status' => $requiresPayment ? RequestStatus::PaymentVerification : RequestStatus::Submitted,
                'evaluation_started_at' => now(),
                'evaluated_by' => $registrar->id,
                'requirements_completed_at' => now(),
            ]);

            $this->record($request, $registrar, RequestEventType::WalkInRecorded, null, $request->status);

            if (! $requiresPayment) {
                $this->accept($request, $request, $registrar);
            }

            return $request;
        });
    }

    /**
     * The walk-in client paid at the counter: record the payment, with its official receipt number
     * and no file, and accept the request (REQ-26-05).
     */
    public function verifyWalkInPayment(CredentialRequest $request, User $registrar, string $receiptNo, string $amount): void
    {
        if (blank($receiptNo)) {
            throw new InvalidTransition('Give the official receipt number.');
        }

        DB::transaction(function () use ($request, $registrar, $receiptNo, $amount): void {
            $locked = $this->lock($request, [RequestStatus::PaymentVerification], 'Only payments awaiting validation can be validated.');

            if (! $locked->isWalkIn()) {
                throw new InvalidTransition('Online payments are validated by the cashier.');
            }

            $proof = $locked->paymentProofs()->make([
                'uploaded_by' => $registrar->id,
                'amount' => $amount,
                'paid_on' => now()->toDateString(),
                'status' => PaymentStatus::Verified,
            ]);
            $proof->forceFill([
                'official_receipt_no' => $receiptNo,
                'reviewed_by' => $registrar->id,
                'reviewed_at' => now(),
            ])->save();

            $this->record($locked, $registrar, RequestEventType::PaymentVerified, null, null, "Official receipt no. {$receiptNo}");
            $this->accept($locked, $request, $registrar);
        });
    }

    /**
     * Link a walk-in request to its client's account, once the account's email is verified
     * (REQ-26-07). A linked request never moves to another account.
     */
    public function linkToAccount(CredentialRequest $request, User $owner): void
    {
        DB::transaction(function () use ($request, $owner): void {
            /** @var CredentialRequest $locked */
            $locked = CredentialRequest::whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isWalkIn() || $locked->user_id !== null) {
                return;
            }

            $locked->forceFill(['user_id' => $owner->id])->save();
            $request->setRawAttributes($locked->getAttributes(), sync: true);
            RequestApplication::whereKey($locked->application_id)->whereNull('user_id')->update(['user_id' => $owner->id]);

            // One entry per application, on its first credential (REQ-27-05).
            if ($locked->isLead()) {
                $this->record($locked, $owner, RequestEventType::LinkedToAccount, null, null);
            }
        });
    }

    /**
     * The verified requester account with this email, if any: never a staff account (REQ-26-08).
     */
    public static function requesterAccount(string $email): ?User
    {
        return User::query()
            ->where('email', Str::lower($email))
            ->where('role', Role::Student)
            ->whereNotNull('email_verified_at')
            ->first();
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
     * The Registrar collects the paper from an office whose task is done and brings it on: to the
     * next office on the route, a corrected document back to the office that returned it, or a
     * returned one to the office it was returned to. That office still has to accept its receipt
     * (REQ-17-08), except the Office of the Registrar itself (REQ-32-09). The Registrar's own
     * received task is marked done in the same step (REQ-17-12).
     * Only registrars forward, from Outgoing (REQ-32-03, 04; the policy checks it).
     */
    public function forwardDocument(OfficeVisit $visit, User $actor): void
    {
        DB::transaction(function () use ($visit, $actor): void {
            [$locked, $current] = $this->lockVisit($visit, [VisitStatus::Done, VisitStatus::Received]);

            if (! $current->load(['department', 'step'])->isForwardable()) {
                throw new InvalidTransition('The office has to finish its task before the document is forwarded.');
            }

            if ($current->isPendingReturn()) {
                // Returned for correction: back to the office it was returned to (REQ-32-08).
                $step = $current->step()->firstOrFail();
                $office = $current->department()->firstOrFail();
                $to = Department::findOrFail($current->returned_to_department_id);

                $this->closeVisit($current, $actor, VisitStatus::Returned);
                $this->forward($locked, $step, $to, VisitPurpose::Correction, $office, $actor);
                $visit->setRawAttributes($current->getAttributes(), sync: true);

                return;
            }

            if ($current->status === VisitStatus::Received) {
                $this->markDone($locked, $current, $actor);
            }

            $this->leave($locked, $current, $actor);
            $visit->setRawAttributes($current->getAttributes(), sync: true);
        });
    }

    /**
     * The Registrar found the credential complete and accurate: complete the final-check visit,
     * which makes the credential ready for release (REQ-16-19, REQ-05-05).
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
     * Return the document to an earlier office for correction, with the reason and, optionally,
     * the corrective action needed (REQ-05-06). The paper stays in the office, in its Outgoing
     * list, until the Registrar brings it back (REQ-32-07, forwardDocument). The office's route
     * step is not completed.
     */
    public function returnVisit(OfficeVisit $visit, User $actor, Department $to, string $reason, ?string $correctiveAction = null, ?string $remarks = null): void
    {
        if (blank($reason)) {
            throw new InvalidTransition('Give the reason for the return.');
        }

        $correctiveAction = filled($correctiveAction) ? $correctiveAction : null;

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

            $current->forceFill([
                'status' => VisitStatus::Done,
                'done_at' => now(),
                'done_by' => $actor->id,
                'returned_to_department_id' => $to->id,
                'return_reason' => $reason,
                'corrective_action' => $correctiveAction,
                'remarks' => filled($remarks) ? $remarks : null,
            ])->save();
            $visit->setRawAttributes($current->getAttributes(), sync: true);

            $this->transition($locked, $locked, RequestStatus::ReturnedForCorrection, $actor, RequestEventType::DocumentReturned,
                "Returned to {$to->name}: {$reason}".($correctiveAction !== null ? "\nCorrective action: {$correctiveAction}" : ''), visit: $current);
        });
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
            // Counted when it was made ready (REQ-11-08); only a request without one gets it here.
            $locked->processing_working_days ??= $locked->accepted_at !== null
                ? $this->calendar->workingDaysBetween($locked->accepted_at, $locked->ready_at ?? now())
                : null;

            $this->transition($locked, $request, RequestStatus::Released, $registrar, RequestEventType::Released,
                "Received by {$recipientName} ({$recipientType->label()})");
        });
    }

    // ── Internals ──────────────────────────────────────────────────────────────────────────

    /** The status in which each stage's checklist is filled in. */
    private const STAGE_STATUS = [
        'evaluation' => RequestStatus::UnderEvaluation,
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
        $this->recordVerifications($locked, $registrar);

        if ($locked->requires_payment) {
            $this->transition($locked, $original, RequestStatus::AwaitingPayment, $registrar, RequestEventType::RequirementsCompleted, $remarks);

            return;
        }

        $locked->save();
        $this->record($locked, $registrar, RequestEventType::RequirementsCompleted, null, null, $remarks);
        $this->accept($locked, $original, $registrar);
    }

    /**
     * The evaluation is complete: each part of the requester's details is verified on this request,
     * when the request's copy is still their saved details. A part already verified with the same
     * details keeps its first verification (REQ-21-01).
     */
    private function recordVerifications(CredentialRequest $locked, User $registrar): void
    {
        $owner = User::query()->findOrFail($locked->user_id);

        foreach (VerifiedDetails::PARTS as $part) {
            if (VerifiedDetails::current($owner, $part) !== null || ! VerifiedDetails::match($part, $owner, $locked)) {
                continue;
            }

            RequesterVerification::query()->upsert(
                [['user_id' => $owner->id, 'part' => $part->value, 'credential_request_id' => $locked->id, 'verified_by' => $registrar->id, 'verified_at' => now()]],
                ['user_id', 'part'],
                ['credential_request_id', 'verified_by', 'verified_at'],
            );
        }
    }

    /**
     * Confirm each part not checked yet whose copy equals the copy the requester's details were
     * verified on, as the registrar who verified them did then; a carried identity marks the
     * request's ID verified (REQ-21-04). The caller saves the request.
     */
    private function carryVerifiedParts(CredentialRequest $locked, User $owner): void
    {
        $checked = $locked->checks()->get()->map(fn (RequestCheck $check): CheckItem => $check->item)->all();

        foreach (VerifiedDetails::PARTS as $part) {
            $verification = in_array($part, $checked, true) ? null : VerifiedDetails::verification($owner, $part);

            if ($verification === null || ! VerifiedDetails::match($part, $locked, $verification->credentialRequest)) {
                continue;
            }

            RequestCheck::query()->insert([
                'credential_request_id' => $locked->id,
                'item' => $part->value,
                'result' => CheckResult::Confirmed->value,
                'checked_by' => $verification->verified_by,
                'checked_at' => $verification->verified_at,
                'carried_from_request_id' => $verification->credential_request_id,
            ]);

            if ($part === CheckItem::Identity) {
                $locked->forceFill([
                    'identity_status' => ReviewStatus::Verified,
                    'identity_reviewed_by' => $verification->verified_by,
                    'identity_reviewed_at' => $verification->verified_at,
                    'identity_remarks' => null,
                ]);
            }
        }

        $locked->unsetRelation('checks');
    }

    /**
     * The requester's corrections (REQ-20-07): the credential, purpose and receiving school, the fee
     * that follows, and a new copy of their details. Returns the checklist parts whose data changed.
     *
     * @param  CredentialItem  $changes
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

        [$terms, $academicYear] = self::termsAndYear($type, $changes);
        // Priced for the owner's status, as on submission (REQ-29-05, 07).
        $fee = $type->feeFor($owner->academic_status, count($terms ?? []));

        $locked->forceFill(['terms' => $terms, 'academic_year' => $academicYear]);
        $locked->fill([
            'credential_type_id' => $type->id,
            'purpose' => $changes['purpose'],
            // As on submission (REQ-04-03).
            'receiving_institution' => $type->requires_receiving_institution ? $receivingInstitution : null,
        ]);
        $locked->forceFill([
            'requester_name' => $owner->requesterName(),
            'sex' => $owner->sex,
            'maiden_name' => $owner->maiden_name,
            'contact_number' => $owner->contact_number,
            'college_id' => $owner->college_id,
            'academic_status' => $owner->academic_status,
            'program' => $owner->program,
            'student_number' => $owner->student_number,
            'attended_from' => $owner->attended_from,
            'attended_to' => $owner->attended_to,
            'requires_payment' => $fee !== null,
            'fee' => $fee,
            'amount_due' => $fee,
        ]);

        $changed = [];

        if ($locked->isDirty(['credential_type_id', 'purpose', 'receiving_institution', 'terms', 'academic_year'])) {
            $changed[] = CheckItem::CredentialRequest;
        }

        if ($locked->isDirty(['requester_name', 'sex', 'maiden_name', 'contact_number'])) {
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
        $this->acceptOne($locked, $original, $registrar, $remarks);

        // Every credential of the application is accepted with it, each with its own route and due
        // date, and moves on its own from here (REQ-27-04).
        if ($locked->isLead()) {
            CredentialRequest::query()
                ->where('application_id', $locked->application_id)
                ->whereKeyNot($locked->getKey())
                ->whereIn('status', RequestStatus::beforeAcceptance())
                ->orderBy('item_no')
                ->lockForUpdate()
                ->get()
                ->each(fn (CredentialRequest $sibling) => $this->acceptOne($sibling, $sibling, $registrar, $remarks));
        }
    }

    private function acceptOne(CredentialRequest $locked, CredentialRequest $original, User $registrar, ?string $remarks = null): void
    {
        $type = $locked->credentialType()->firstOrFail();
        $template = $type->steps()->get();

        if ($template->isEmpty()) {
            throw new InvalidTransition('This credential type has no route. Add its offices before accepting requests.');
        }

        $registrarOffice = Department::where('code', Department::REGISTRAR_CODE)->first()
            ?? throw new InvalidTransition('The Office of the Registrar (code REG) is missing.');

        $route = [
            ...$template->map(fn ($step): array => [
                'department_id' => $step->is_student_college ? $locked->college_id : $step->department_id,
                'task' => $step->task,
                'allotted_days' => $step->allotted_days,   // REQ-30-04
                'instructions' => $step->instructions,
            ])->all(),
            [
                'department_id' => $registrarOffice->id,
                'task' => RouteTask::FinalCheck,
                'allotted_days' => null,   // the request's due date applies (REQ-30-06)
                'instructions' => 'Confirm the completeness and accuracy of the credential before marking it ready for release.',
            ],
        ];

        foreach ($route as $index => $step) {
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

        // The processing clock stops here (REQ-34-11, REQ-11-08).
        $locked->ready_at = now();
        $locked->processing_working_days = $locked->accepted_at !== null
            ? $this->calendar->workingDaysBetween($locked->accepted_at, now())
            : null;
        $this->transition($locked, $locked, RequestStatus::ReadyForRelease, $actor, RequestEventType::ReadyForRelease);
    }

    /**
     * The document is physically in the office: the receiving office, staff member and time are
     * recorded (REQ-05-03, REQ-17-08).
     */
    private function receive(CredentialRequest $locked, OfficeVisit $current, User $actor): void
    {
        $step = $current->step()->firstOrFail();

        // The office's own deadline, counted from this receipt; none for a correction or the final
        // check, where the request's due date applies (REQ-30-05, 06).
        $deadline = $current->purpose !== VisitPurpose::Correction && $step->allotted_days !== null
            ? $this->calendar->addWorkingDays(now(), $step->allotted_days)
            : null;

        $current->forceFill(['status' => VisitStatus::Received, 'received_at' => now(), 'received_by' => $actor->id, 'due_at' => $deadline])->save();

        if ($current->purpose === VisitPurpose::Correction) {
            $this->record($locked, $actor, RequestEventType::ReceiptAccepted, null, null, null, visit: $current);

            return;
        }

        // The first office's receipt keeps the request accepted for processing; it is in
        // progress once sent on (REQ-16-18). A corrected document received back resumes it.
        $to = match (true) {
            $step->task === RouteTask::FinalCheck => RequestStatus::ForFinalApproval,
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
     * that does not move (sent on within the same office, REQ-17-05) or that comes to the Office
     * of the Registrar, which carries every paper and so already has it in hand (REQ-32-09), is
     * received at once.
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

        if ($to->code === Department::REGISTRAR_CODE || $from?->is($to) === true) {
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

        // Until acceptance the application is acted on through its first credential (REQ-27-03).
        if (! $locked->isLead() && $locked->status->isBeforeAcceptance()) {
            throw new InvalidTransition('Act on the application\'s first credential, '.$locked->application()->value('reference_no').'-1.');
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

        if ($locked->isLead() && $from->isBeforeAcceptance() && $to !== RequestStatus::AcceptedForProcessing) {
            $this->mirrorToSiblings($locked);
        }

        return $this->record($locked, $actor, $type, $from, $to, $remarks, $step, $visit);
    }

    /** What the other credentials of an application share with the first until acceptance (REQ-27-03). */
    private const MIRRORED = [
        'status', 'evaluation_started_at', 'evaluated_by', 'requirements_completed_at',
        'identity_status', 'identity_reviewed_by', 'identity_reviewed_at', 'identity_remarks',
        'declined_at', 'decline_reason', 'cancelled_at', 'cancel_reason',
    ];

    /**
     * The other credentials take the first's status and evaluation, with no ledger entry of their
     * own, so the requester is told once (REQ-27-03, 05).
     */
    private function mirrorToSiblings(CredentialRequest $lead): void
    {
        $values = collect(self::MIRRORED)->mapWithKeys(fn (string $column): array => [$column => $lead->getAttributes()[$column] ?? null])->all();

        CredentialRequest::query()
            ->where('application_id', $lead->application_id)
            ->whereKeyNot($lead->getKey())
            ->update([...$values, 'updated_at' => now()]);
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

    /** The proof under review is verified or rejected; no receipt number is kept (REQ-16-17). */
    private function reviewLatestProof(CredentialRequest $request, User $registrar, PaymentStatus $status, ?string $remarks = null): void
    {
        $proof = $request->paymentProofs()->where('status', PaymentStatus::Submitted)->first();

        if ($proof === null) {
            throw new InvalidTransition('There is no proof of payment waiting for verification.');
        }

        $proof->forceFill([
            'status' => $status,
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
