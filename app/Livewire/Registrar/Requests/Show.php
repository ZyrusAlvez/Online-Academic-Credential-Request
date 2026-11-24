<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\CheckItem;
use App\Enums\CheckResult;
use App\Enums\CheckStage;
use App\Enums\RegistrarQueue;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\ActsOnVisits;
use App\Livewire\Concerns\ChoosesRecipient;
use App\Livewire\Concerns\ReturnsToList;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\RequestCheck;
use App\Models\RequestRepresentative;
use App\Services\RequestWorkflow;
use App\Support\RegistrarLink;
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
    use ActsOnVisits {
        attempt as runTransition;
    }
    use ChoosesRecipient;
    use ReturnsToList;
    use TrimsInput;

    /** What the page shows, by the request's stage. */
    public const PANEL_LOCKED = 'locked';

    public const PANEL_START = 'start';

    public const PANEL_EVALUATION = 'evaluation';

    public const PANEL_CLARIFICATION = 'clarification';

    public const PANEL_COMPLIANCE = 'compliance';

    /** A walk-in payment, validated with its receipt number (REQ-26-05). */
    public const PANEL_PAYMENT = 'payment';

    public const PANEL_FINAL_CHECK = 'final_check';

    public const PANEL_RELEASE = 'release';

    public const PANEL_VIEW = 'view';

    /** Require Office Visit: the "Other" checkbox's value among the ticked items (REQ-20-04). */
    public const OTHER_ITEM = '__other__';

    #[Locked]
    public CredentialRequest $credentialRequest;

    /** The queue the page is under (REQ-16-26); null for a closed request opened directly. */
    #[Locked]
    public ?RegistrarQueue $currentQueue = null;

    public string $remarks = '';

    /** Walk-in Payment Validation: the official receipt number and the amount paid (REQ-26-05). */
    public string $receiptNo = '';

    public string $amountPaid = '';

    /**
     * Require Office Visit: the credential's requirements ticked as required at the office, and
     * OTHER_ITEM when "Other" is ticked (REQ-20-04).
     *
     * @var list<string>
     */
    public array $requiredItems = [];

    /** Require Office Visit: what "Other" is, in the evaluator's words; required once it is ticked. */
    public string $otherRequirement = '';

    #[Locked]
    public ?int $rejectingRepresentativeId = null;

    public string $representativeRemarks = '';

    /** The decision that could not run, and why (REQ-17-17). */
    #[Locked]
    public string $blockedAction = '';

    /**
     * What blocks a decision: each part not confirmed, an office-visit item not received (REQ-17-17).
     *
     * @var list<array{label: string, state: 'rejected'|'unchecked'|'to_receive'}>
     */
    #[Locked]
    public array $blockedParts = [];

    /** @var list<string> */
    #[Locked]
    public array $blockers = [];

    /**
     * @param  RegistrarQueue|string|null  $queue  the route's default; a page opened under another
     *                                             queue than the request's moves there (REQ-16-26)
     */
    public function mount(CredentialRequest $credentialRequest, RegistrarQueue|string|null $queue = null): void
    {
        $this->authorize('review', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
        $current = RegistrarQueue::forRequest($credentialRequest);
        $this->currentQueue = $current;

        // Until acceptance the application is worked on through its first credential (REQ-27-03).
        $follower = ! $credentialRequest->isLead() && $credentialRequest->status->isBeforeAcceptance();

        if ($follower || ($queue !== null && $current !== (is_string($queue) ? RegistrarQueue::from($queue) : $queue))) {
            $this->redirect(RegistrarLink::to($credentialRequest), navigate: true);

            return;
        }

        $this->rememberList($current?->routeName() ?? 'records');
    }

    /**
     * Run a transition; when it moves the request out of the queue the page is under, go back to
     * that queue's list as the registrar left it (REQ-16-26).
     */
    protected function attempt(Closure $transition, string $success): bool
    {
        if (! $this->runTransition($transition, $success)) {
            return false;
        }

        $request = $this->credentialRequest->fresh();

        if ($request === null || RegistrarQueue::forRequest($request) !== $this->currentQueue) {
            $this->backToList($success);
        }

        return true;
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
        $this->authorizeDecision();
        $request = $this->credentialRequest;
        $optional = ['remarks' => ['nullable', 'string', 'max:500']];

        $decision = match ($request->status) {
            RequestStatus::UnderEvaluation => ['approve', $optional,
                fn () => $workflow->approveEvaluation($request, $this->actor(), $this->remarks),
                $request->requires_payment ? 'Approved. Payment instructions were sent to the requester.' : 'Approved and accepted for processing.'],
            // A walk-in payment is validated with its receipt number instead (REQ-26-05).
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
     * Return for clarification at the evaluation (REQ-20-03). A walk-in payment is never returned:
     * the client is at the counter (REQ-26-05).
     */
    public function returnRequest(RequestWorkflow $workflow): void
    {
        $this->authorizeDecision();
        $request = $this->credentialRequest;
        $rules = ['remarks' => ['required', 'string', 'max:500']];

        $decision = match ($request->status) {
            RequestStatus::UnderEvaluation => ['return', $rules,
                fn () => $workflow->returnForClarification($request, $this->actor(), $this->remarks),
                'Returned to the requester for clarification. They can edit the request and resubmit it.'],
            default => null,
        };

        if ($decision === null) {
            $this->dispatch('notify', message: 'This request cannot be returned at this stage.');

            return;
        }

        $this->decide(...$decision);
    }

    /**
     * A walk-in client paid at the counter: the official receipt number and the amount paid, then
     * the request is accepted (REQ-26-05).
     */
    public function validatePayment(RequestWorkflow $workflow): void
    {
        $this->authorize('validatePayment', $this->credentialRequest);

        $this->validate([
            'receiptNo' => ['required', 'string', 'max:50'],
            'amountPaid' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ], attributes: ['receiptNo' => 'official receipt number', 'amountPaid' => 'amount paid']);

        if ($this->attempt(fn () => $workflow->verifyWalkInPayment($this->credentialRequest, $this->actor(), $this->receiptNo, (string) $this->amountPaid),
            'Payment validated and request accepted. The processing period has started.')) {
            $this->reset('receiptNo', 'amountPaid');
        }
    }

    /**
     * Decline at the evaluation, payment validation or final quality check (REQ-16-14, 17, 19).
     */
    public function decline(RequestWorkflow $workflow): void
    {
        $this->authorizeDecision();

        $this->decide(
            'decline',
            ['remarks' => ['required', 'string', 'max:500']],
            fn () => $workflow->decline($this->credentialRequest, $this->actor(), $this->remarks),
            'Request declined. The requester was notified.',
        );
    }

    /**
     * Require Office Visit, from its dialog: what is required at the office, and optional remarks
     * (REQ-20-04).
     */
    public function requireOfficeVisit(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        abort_unless($this->credentialRequest->status === RequestStatus::UnderEvaluation, 404);

        $listed = $this->credentialRequest->credentialType->requirements ?? [];
        $other = in_array(self::OTHER_ITEM, $this->requiredItems, true);

        $this->validate([
            'requiredItems' => ['required', 'array'],
            'requiredItems.*' => ['string', Rule::in([...$listed, self::OTHER_ITEM])],
            'otherRequirement' => [Rule::requiredIf($other), 'nullable', 'string', 'max:250'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'requiredItems.required' => 'Tick at least one requirement, or Other.',
            'otherRequirement.required' => 'Write what else is required under Other.',
        ], ['otherRequirement' => 'other requirement']);

        // Text typed under Other counts only while Other is ticked.
        $items = [...array_values(array_intersect($listed, $this->requiredItems)), ...($other ? [$this->otherRequirement] : [])];

        if ($this->attempt(fn () => $workflow->requireOfficeVisit($this->credentialRequest, $this->actor(), $items, $this->remarks),
            'The requester was asked to visit the Office of the Registrar.')) {
            Flux::modal('office-visit')->close();
            $this->reset('requiredItems', 'otherRequirement', 'remarks');
        }
    }

    /**
     * A required item was submitted at the office (REQ-20-09).
     */
    public function markReceived(int $deficiencyId, RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $deficiency = $this->credentialRequest->deficiencies()->findOrFail($deficiencyId);
        $this->attempt(fn () => $workflow->markReceived($deficiency, $this->actor()), 'Marked received.');
        $this->credentialRequest->unsetRelation('deficiencies');
    }

    /**
     * Every required item was received: on to payment (REQ-20-09).
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

        $recipient = $this->chosenRecipient($this->credentialRequest);

        $this->attempt(fn () => $workflow->release(
            $this->credentialRequest,
            $this->actor(),
            $recipient['recipient_type'],
            $recipient['representative_id'],
            representativeName: $recipient['representative_name'],
        ), 'Credential marked released.');
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'identityDocument', 'credentialType', 'college', 'evaluator', 'checks.checker', 'checks.carriedFrom',
            'openVisit.department', 'openVisit.fromDepartment', 'openVisit.step', 'openVisit.credentialRequest.visits', 'openVisit.credentialRequest.steps.department',
            'paymentProofs.reviewer', 'deficiencies.resolver', 'representatives.documents', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.doer', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ]);

        $panel = $this->panel($request);
        // Only Initial Evaluation has a checklist (REQ-16-17, 19).
        $stage = in_array($panel, [self::PANEL_EVALUATION, self::PANEL_CLARIFICATION], true) ? CheckStage::Evaluation : null;

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            // The application's credentials, listed until acceptance (REQ-27-09).
            'credentials' => CredentialRequest::query()->where('application_id', $request->application_id)
                ->with('credentialType:id,name')->orderBy('item_no')->get(),
            'panel' => $panel,
            'items' => $stage !== null ? CheckItem::for($stage, $request) : [],
            'results' => $stage !== null ? $request->checkResults($stage) : [],
            // Parts confirmed from an earlier verification, by item (REQ-21-05).
            'carried' => $request->checks->whereNotNull('carried_from_request_id')->keyBy(fn (RequestCheck $check): string => $check->item->value),
            'verifiedRepresentatives' => $request->representatives->where('status', ReviewStatus::Verified)->values(),
            'lastReturn' => $request->events->last(fn ($event) => in_array($event->type, [RequestEventType::CustomerActionRequested, RequestEventType::ComplianceRequested], true)),
            // What the requester wrote when resubmitting, for the evaluation that follows (REQ-20-14).
            'resubmitNote' => $panel === self::PANEL_EVALUATION
                ? (string) $request->events->last(fn ($event) => $event->type === RequestEventType::Resubmitted)?->remarks
                : '',
        ])->title($request->reference_no);
    }

    /**
     * A stage decision; at Payment Validation only for a walk-in request, since online payments
     * are the cashier's (REQ-25-07).
     */
    private function authorizeDecision(): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        if ($this->credentialRequest->status === RequestStatus::PaymentVerification) {
            $this->authorize('validatePayment', $this->credentialRequest);
        }
    }

    /**
     * The stage panel for the request's current state (design of spec 16).
     */
    private function panel(CredentialRequest $request): string
    {
        $visit = $request->openVisit;
        $atFinalCheck = $request->isAtFinalCheck();

        return match (true) {
            $request->isEvaluatedBySomeoneElse($this->actor()) => self::PANEL_LOCKED,
            $request->status === RequestStatus::Submitted => self::PANEL_START,
            $request->status === RequestStatus::UnderEvaluation => self::PANEL_EVALUATION,
            $request->status === RequestStatus::ReturnedForClarification => self::PANEL_CLARIFICATION,
            $request->status === RequestStatus::PendingCompliance => self::PANEL_COMPLIANCE,
            $request->status === RequestStatus::PaymentVerification && $request->isWalkIn() => self::PANEL_PAYMENT,
            $atFinalCheck => self::PANEL_FINAL_CHECK,
            $request->status === RequestStatus::ReadyForRelease => self::PANEL_RELEASE,
            // A paper at an office belongs to no queue; it opens on Records (REQ-32-10).
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
        } elseif ($action === 'return' && $this->stage() === CheckStage::Evaluation && ! in_array('rejected', array_column($this->stageParts(), 'state'), true)) {
            $blockers[] = 'Reject at least one part of the checklist, so the requester knows what to correct.';
        } elseif ($action === 'confirm') {
            $parts = $this->credentialRequest->deficiencies()->whereNull('resolved_at')->pluck('description')
                ->map(fn (string $item): array => ['label' => $item, 'state' => 'to_receive'])->values()->all();
        }

        try {
            $this->validate($rules, ['remarks.required' => 'Write your remarks; the requester sees them.']);
        } catch (ValidationException $e) {
            // The field is marked too, so the exception goes on to Livewire.
            $messages = array_values(array_map(strval(...), Arr::flatten($e->errors())));
            $this->block($action, $parts, [...$blockers, ...$messages]);

            throw $e;
        }

        if ($parts !== [] || $blockers !== []) {
            $this->block($action, $parts, $blockers);

            return;
        }

        if ($this->attempt($decision, $success)) {
            $this->reset('remarks');
        }
    }

    /** The checklist stage the request is at, if any: only Initial Evaluation has one (REQ-16-17). */
    private function stage(): ?CheckStage
    {
        return $this->credentialRequest->status === RequestStatus::UnderEvaluation ? CheckStage::Evaluation : null;
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
     * @return list<array{label: string, state: 'rejected'|'unchecked'}>
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
     * @param  list<array{label: string, state: 'rejected'|'unchecked'|'to_receive'}>  $parts
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
