<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Enums\RouteTask;
use App\Enums\VisitPurpose;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\ActsOnVisits;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\RequestCheck;
use App\Models\RequestRepresentative;
use App\Services\RequestWorkflow;
use App\Support\ReturnOffices;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The registrar's working page for one request: the checklist and decision of its current stage
 * (spec 16), the Office of the Registrar's own route steps (spec 05) and the release (spec 11).
 */
class Show extends Component
{
    use ActsOnVisits;
    use TrimsInput;

    /** What the page shows, by the request's stage. */
    public const PANEL_LOCKED = 'locked';

    public const PANEL_START = 'start';

    public const PANEL_EVALUATION = 'evaluation';

    public const PANEL_CLARIFICATION = 'clarification';

    public const PANEL_COMPLIANCE = 'compliance';

    public const PANEL_PAYMENT = 'payment';

    public const PANEL_FINAL_CHECK = 'final_check';

    public const PANEL_RELEASE = 'release';

    public const PANEL_OFFICE = 'office';

    public const PANEL_VIEW = 'view';

    /** Listed as a blocking part of Approve, not as a sentence (REQ-17-17). */
    private const RECEIPT_REQUIRED = 'Enter the official receipt number.';

    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $remarks = '';

    public string $officialReceiptNo = '';

    /**
     * Require Office Visit: the credential's requirements ticked as missing (REQ-20-04).
     *
     * @var list<string>
     */
    public array $missing = [];

    /** Require Office Visit: anything else missing, in the evaluator's words. */
    public string $otherMissing = '';

    #[Locked]
    public ?int $rejectingRepresentativeId = null;

    public string $representativeRemarks = '';

    public string $recipientType = 'owner';

    public int|string|null $recipientRepresentativeId = null;

    /** The decision that could not run, and why (REQ-17-17). */
    #[Locked]
    public string $blockedAction = '';

    /**
     * What blocks Approve: each part not confirmed and a missing receipt number (REQ-17-17).
     *
     * @var list<array{label: string, state: 'rejected'|'unchecked'|'missing'}>
     */
    #[Locked]
    public array $blockedParts = [];

    /** @var list<string> */
    #[Locked]
    public array $blockers = [];

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('review', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
    }

    public function startEvaluation(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $this->attempt(fn () => $workflow->startEvaluation($this->credentialRequest, $this->actor()), 'Evaluation started. The request is yours until you decide.');
    }

    /**
     * Confirm or reject one part of the current stage's checklist (REQ-16-10, 17, 19).
     */
    public function check(string $item, string $result, RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $checkItem = CheckItem::tryFrom($item);
        $checkResult = CheckResult::tryFrom($result);
        abort_if($checkItem === null || $checkResult === null, 422);

        try {
            $workflow->recordCheck($this->credentialRequest, $this->actor(), $checkItem, $checkResult);
        } catch (InvalidTransition $e) {
            $this->dispatch('notify', message: $e->getMessage());
        }

        $this->credentialRequest->unsetRelation('checks');
    }

    /**
     * The stage's Approve (REQ-16-12, 17, 19).
     */
    public function approve(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        $request = $this->credentialRequest;
        $optional = ['remarks' => ['nullable', 'string', 'max:500']];

        $decision = match ($request->status) {
            RequestStatus::UnderEvaluation => ['approve', $optional,
                fn () => $workflow->approveEvaluation($request, $this->actor(), $this->remarks),
                $request->requires_payment ? 'Approved. Payment instructions were sent to the requester.' : 'Approved and accepted for processing.'],
            RequestStatus::PaymentVerification => ['approve', $optional + ['officialReceiptNo' => ['required', 'string', 'max:50']],
                fn () => $workflow->verifyPaymentAndAccept($request, $this->actor(), $this->officialReceiptNo, $this->remarks),
                'Payment verified and request accepted. The processing period has started.'],
            RequestStatus::ForFinalApproval => ['approve', $optional,
                fn () => $workflow->approveFinalCheck($request, $this->actor(), $this->remarks),
                'Approved. The credential is ready for release and the requester was notified.'],
            default => null,
        };

        if ($decision === null) {
            $this->dispatch('notify', message: 'There is nothing to approve at this stage.');

            return;
        }

        $this->decide(...$decision);
    }

    /**
     * Return: for clarification at the evaluation, the proof of payment at the payment validation
     * (REQ-20-03, REQ-16-17).
     */
    public function returnRequest(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        $request = $this->credentialRequest;
        $rules = ['remarks' => ['required', 'string', 'max:500']];

        $decision = match ($request->status) {
            RequestStatus::UnderEvaluation => ['return', $rules,
                fn () => $workflow->returnForClarification($request, $this->actor(), $this->remarks),
                'Returned to the requester for clarification. They can edit the request and resubmit it.'],
            RequestStatus::PaymentVerification => ['return', $rules,
                fn () => $workflow->rejectPayment($request, $this->actor(), $this->remarks),
                'Proof of payment returned. The requester was asked for a valid one.'],
            default => null,
        };

        if ($decision === null) {
            $this->dispatch('notify', message: 'This request cannot be returned at this stage.');

            return;
        }

        $this->decide(...$decision);
    }

    /**
     * Decline at the evaluation, payment validation or final quality check (REQ-16-14, 17, 19).
     */
    public function decline(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $this->decide(
            'decline',
            ['remarks' => ['required', 'string', 'max:500']],
            fn () => $workflow->decline($this->credentialRequest, $this->actor(), $this->remarks),
            'Request declined. The requester was notified.',
        );
    }

    /**
     * Require Office Visit, from its dialog: what is missing and the remarks (REQ-20-04).
     */
    public function requireOfficeVisit(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        abort_unless($this->credentialRequest->status === RequestStatus::UnderEvaluation, 404);

        $listed = $this->credentialRequest->credentialType->requirements ?? [];

        $this->validate([
            'missing' => ['array'],
            'missing.*' => ['string', Rule::in($listed)],
            'otherMissing' => [Rule::requiredIf($this->missing === []), 'nullable', 'string', 'max:250'],
            'remarks' => ['required', 'string', 'max:500'],
        ], [
            'otherMissing.required' => 'Tick at least one missing requirement, or describe it under Other.',
            'remarks.required' => 'Write your remarks; the requester sees them.',
        ], ['otherMissing' => 'other missing requirement']);

        $missing = [...array_values(array_intersect($listed, $this->missing)), ...(filled($this->otherMissing) ? [$this->otherMissing] : [])];

        if ($this->attempt(fn () => $workflow->requireOfficeVisit($this->credentialRequest, $this->actor(), $missing, $this->remarks),
            'The requester was asked to bring the missing requirements to the Office of the Registrar.')) {
            Flux::modal('office-visit')->close();
            $this->reset('missing', 'otherMissing', 'remarks');
        }
    }

    /**
     * A missing requirement was submitted at the office (REQ-20-09).
     */
    public function markReceived(int $deficiencyId, RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $deficiency = $this->credentialRequest->deficiencies()->findOrFail($deficiencyId);
        $this->attempt(fn () => $workflow->markReceived($deficiency, $this->actor()), 'Marked received.');
        $this->credentialRequest->unsetRelation('deficiencies');
    }

    /**
     * Every missing requirement was received: on to payment (REQ-20-09).
     */
    public function confirmCompliance(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        $request = $this->credentialRequest;

        $this->decide(
            'confirm',
            ['remarks' => ['nullable', 'string', 'max:500']],
            fn () => $workflow->confirmCompliance($request, $this->actor(), $this->remarks),
            $request->requires_payment ? 'Compliance confirmed. Payment instructions were sent to the requester.' : 'Compliance confirmed and the request accepted for processing.',
        );
    }

    // ── Representatives declared after the evaluation, and release (REQ-16-20) ──────────────

    public function verifyRepresentative(int $representativeId, RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);

        $this->attempt(fn () => $workflow->reviewRepresentative($this->representative($representativeId), $this->actor(), true), 'Representative verified.');
    }

    public function confirmRejectRepresentative(int $representativeId): void
    {
        $this->representative($representativeId);
        $this->rejectingRepresentativeId = $representativeId;
        $this->reset('representativeRemarks');
        Flux::modal('reject-representative')->show();
    }

    public function rejectRepresentative(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['representativeRemarks' => ['required', 'string', 'max:500']], attributes: ['representativeRemarks' => 'reason']);

        $representative = $this->representative((int) $this->rejectingRepresentativeId);

        if ($this->attempt(fn () => $workflow->reviewRepresentative($representative, $this->actor(), false, $this->representativeRemarks), 'Representative not accepted.')) {
            Flux::modal('reject-representative')->close();
            $this->reset('rejectingRepresentativeId', 'representativeRemarks');
        }
    }

    public function release(RequestWorkflow $workflow): void
    {
        $this->authorize('release', $this->credentialRequest);
        $this->validate([
            'recipientType' => ['required', Rule::enum(RecipientType::class)],
            'recipientRepresentativeId' => [Rule::requiredIf($this->recipientType === RecipientType::Representative->value), 'nullable', 'integer'],
        ], attributes: ['recipientRepresentativeId' => 'representative']);

        $this->attempt(fn () => $workflow->release(
            $this->credentialRequest,
            $this->actor(),
            RecipientType::from($this->recipientType),
            filled($this->recipientRepresentativeId) ? (int) $this->recipientRepresentativeId : null,
        ), 'Credential marked released.');
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'identityDocument', 'credentialType', 'college', 'evaluator', 'checks.checker', 'checks.carriedFrom',
            'openVisit.department', 'openVisit.fromDepartment', 'openVisit.step', 'openVisit.credentialRequest.visits', 'openVisit.credentialRequest.steps.department',
            'paymentProofs.reviewer', 'deficiencies.resolver', 'representatives.documents', 'requirementDocuments', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.doer', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ]);

        $panel = $this->panel($request);
        $stage = match ($panel) {
            self::PANEL_EVALUATION, self::PANEL_CLARIFICATION => CheckStage::Evaluation,
            self::PANEL_PAYMENT => CheckStage::Payment,
            default => null,
        };

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            'panel' => $panel,
            'items' => $stage !== null ? CheckItem::for($stage, $request) : [],
            'results' => $stage !== null ? $request->checkResults($stage) : [],
            // Parts confirmed from an earlier verification, by item (REQ-21-05).
            'carried' => $request->checks->whereNotNull('carried_from_request_id')->keyBy(fn (RequestCheck $check): string => $check->item->value),
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            'returnOffices' => ReturnOffices::for($request),
            'verifiedRepresentatives' => $request->representatives->where('status', ReviewStatus::Verified)->values(),
            'lastReturn' => $request->events->last(fn ($event) => in_array($event->type, [RequestEventType::CustomerActionRequested, RequestEventType::ComplianceRequested], true)),
        ])->title($request->reference_no);
    }

    /**
     * The stage panel for the request's current state (design of spec 16).
     */
    private function panel(CredentialRequest $request): string
    {
        $visit = $request->openVisit;
        $atFinalCheck = $visit !== null && $visit->step->task === RouteTask::FinalCheck && $visit->purpose !== VisitPurpose::Correction;

        return match (true) {
            $request->isEvaluatedBySomeoneElse($this->actor()) => self::PANEL_LOCKED,
            $request->status === RequestStatus::Submitted => self::PANEL_START,
            $request->status === RequestStatus::UnderEvaluation => self::PANEL_EVALUATION,
            $request->status === RequestStatus::ReturnedForClarification => self::PANEL_CLARIFICATION,
            $request->status === RequestStatus::PendingCompliance => self::PANEL_COMPLIANCE,
            $request->status === RequestStatus::PaymentVerification => self::PANEL_PAYMENT,
            $atFinalCheck => self::PANEL_FINAL_CHECK,
            $request->status === RequestStatus::ReadyForRelease => self::PANEL_RELEASE,
            $visit !== null && $this->actor()->can('act', $visit) => self::PANEL_OFFICE,
            default => self::PANEL_VIEW,
        };
    }

    /**
     * Check the decision's conditions, run it and clear the form on success. A decision that can't
     * run opens a dialog listing every reason and keeps what was typed (REQ-17-17).
     *
     * @param  string  $action  approve, return, decline or confirm (compliance)
     * @param  array<string, list<string>>  $rules
     */
    private function decide(string $action, array $rules, Closure $decision, string $success): void
    {
        $parts = [];
        $blockers = [];

        if ($action === 'approve') {
            $parts = $this->unconfirmedParts();

            if (array_key_exists('officialReceiptNo', $rules) && blank($this->officialReceiptNo)) {
                $parts[] = ['label' => 'Official receipt number', 'state' => 'missing'];
            }
        } elseif ($action === 'return' && $this->stage() === CheckStage::Evaluation && ! in_array('rejected', array_column($this->stageParts(), 'state'), true)) {
            $blockers[] = 'Reject at least one part of the checklist, so the requester knows what to correct.';
        } elseif ($action === 'confirm') {
            $parts = $this->credentialRequest->deficiencies()->whereNull('resolved_at')->pluck('description')
                ->map(fn (string $item): array => ['label' => $item, 'state' => 'missing'])->values()->all();
        }

        try {
            $this->validate($rules, [
                'remarks.required' => 'Write your remarks; the requester sees them.',
                'officialReceiptNo.required' => self::RECEIPT_REQUIRED,
            ], ['officialReceiptNo' => 'official receipt number']);
        } catch (ValidationException $e) {
            // The receipt number is already in the parts. The field is marked too, so the exception
            // goes on to Livewire.
            $messages = array_values(array_diff(array_map(strval(...), Arr::flatten($e->errors())), [self::RECEIPT_REQUIRED]));
            $this->block($action, $parts, [...$blockers, ...$messages]);

            throw $e;
        }

        if ($parts !== [] || $blockers !== []) {
            $this->block($action, $parts, $blockers);

            return;
        }

        if ($this->attempt($decision, $success)) {
            $this->reset('remarks', 'officialReceiptNo');
        }
    }

    /** The checklist stage the request is at, if any. */
    private function stage(): ?CheckStage
    {
        return match ($this->credentialRequest->status) {
            RequestStatus::UnderEvaluation => CheckStage::Evaluation,
            RequestStatus::PaymentVerification => CheckStage::Payment,
            default => null,
        };
    }

    /**
     * Every part of the current stage with its state, in checklist order.
     *
     * @return list<array{label: string, state: 'confirmed'|'rejected'|'unchecked'}>
     */
    private function stageParts(): array
    {
        $stage = $this->stage();

        if ($stage === null) {
            return [];
        }

        $results = $this->credentialRequest->checkResults($stage);

        return array_map(fn (CheckItem $item): array => ['label' => $item->label(), 'state' => match ($results[$item->value] ?? null) {
            CheckResult::Confirmed => 'confirmed',
            CheckResult::Rejected => 'rejected',
            default => 'unchecked',
        }], CheckItem::for($stage, $this->credentialRequest));
    }

    /**
     * The parts that keep Approve from running: rejected first, then not checked yet
     * (REQ-16-12, 17, 19).
     *
     * @return list<array{label: string, state: 'rejected'|'unchecked'|'missing'}>
     */
    private function unconfirmedParts(): array
    {
        $rejected = [];
        $unchecked = [];

        foreach ($this->stageParts() as $part) {
            if ($part['state'] === 'rejected') {
                $rejected[] = ['label' => $part['label'], 'state' => 'rejected'];
            } elseif ($part['state'] === 'unchecked') {
                $unchecked[] = ['label' => $part['label'], 'state' => 'unchecked'];
            }
        }

        return [...$rejected, ...$unchecked];
    }

    /**
     * @param  list<array{label: string, state: 'rejected'|'unchecked'|'missing'}>  $parts
     * @param  list<string>  $reasons
     */
    private function block(string $action, array $parts, array $reasons): void
    {
        $this->blockedAction = $action;
        $this->blockedParts = $parts;
        $this->blockers = $reasons;
        Flux::modal('decision-blocked')->show();
    }

    private function representative(int $id): RequestRepresentative
    {
        return $this->credentialRequest->representatives()->findOrFail($id);
    }
}
