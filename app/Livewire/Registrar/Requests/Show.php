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

    public const PANEL_RETURNED = 'returned';

    public const PANEL_PAYMENT = 'payment';

    public const PANEL_FINAL_CHECK = 'final_check';

    public const PANEL_RELEASE = 'release';

    public const PANEL_OFFICE = 'office';

    public const PANEL_VIEW = 'view';

    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $remarks = '';

    public string $officialReceiptNo = '';

    #[Locked]
    public ?int $rejectingRepresentativeId = null;

    public string $representativeRemarks = '';

    public string $recipientType = 'owner';

    public int|string|null $recipientRepresentativeId = null;

    /** The decision that could not run, and why (REQ-17-17). */
    #[Locked]
    public string $blockedAction = '';

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
     * Return: to the requester at the evaluation, the proof of payment at the payment validation
     * (REQ-16-13, 17).
     */
    public function returnRequest(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);
        $request = $this->credentialRequest;
        $rules = ['remarks' => ['required', 'string', 'max:500']];

        $decision = match ($request->status) {
            RequestStatus::UnderEvaluation => ['return', $rules,
                fn () => $workflow->returnToRequester($request, $this->actor(), $this->remarks),
                'Returned to the requester with the rejected parts and your remarks.'],
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
     * The requester complied in person (REQ-16-16).
     */
    public function reopenEvaluation(RequestWorkflow $workflow): void
    {
        $this->authorize('evaluate', $this->credentialRequest);

        $this->attempt(fn () => $workflow->reopenEvaluation($this->credentialRequest, $this->actor()), 'Evaluation reopened. The request is yours until you decide.');
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
            'student', 'identityDocument', 'credentialType', 'college', 'evaluator', 'checks',
            'openVisit.department', 'openVisit.step', 'openVisit.credentialRequest.visits', 'openVisit.credentialRequest.steps.department',
            'paymentProofs.reviewer', 'deficiencies.resolver', 'representatives.documents', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.doer', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ]);

        $panel = $this->panel($request);
        $stage = match ($panel) {
            self::PANEL_EVALUATION, self::PANEL_RETURNED => CheckStage::Evaluation,
            self::PANEL_PAYMENT => CheckStage::Payment,
            self::PANEL_FINAL_CHECK => CheckStage::FinalCheck,
            default => null,
        };

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            'panel' => $panel,
            'items' => $stage !== null ? CheckItem::for($stage, $request) : [],
            'results' => $stage !== null ? $request->checkResults($stage) : [],
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            'returnOffices' => ReturnOffices::for($request),
            'verifiedRepresentatives' => $request->representatives->where('status', ReviewStatus::Verified)->values(),
            'lastReturn' => $request->events->last(fn ($event) => $event->type === RequestEventType::ComplianceRequested),
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
            $request->status === RequestStatus::PendingCompliance => self::PANEL_RETURNED,
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
     * @param  string  $action  approve, return or decline
     * @param  array<string, list<string>>  $rules
     */
    private function decide(string $action, array $rules, Closure $decision, string $success): void
    {
        $blockers = $this->checklistBlockers($action);

        try {
            $this->validate($rules, [
                'remarks.required' => 'Write your remarks; the requester sees them.',
                'officialReceiptNo.required' => 'Enter the official receipt number.',
            ], ['officialReceiptNo' => 'official receipt number']);
        } catch (ValidationException $e) {
            // The field is marked too, so the exception goes on to Livewire.
            $this->block($action, [...$blockers, ...array_values(Arr::flatten($e->errors()))]);

            throw $e;
        }

        if ($blockers !== []) {
            $this->block($action, $blockers);

            return;
        }

        if ($this->attempt($decision, $success)) {
            $this->reset('remarks', 'officialReceiptNo');
        }
    }

    /**
     * What the current stage's checklist lacks for the action: every part confirmed to approve,
     * a rejected part to return at the evaluation (REQ-16-12, 13, 17, 19).
     *
     * @return list<string>
     */
    private function checklistBlockers(string $action): array
    {
        $request = $this->credentialRequest;
        $stage = match ($request->status) {
            RequestStatus::UnderEvaluation => CheckStage::Evaluation,
            RequestStatus::PaymentVerification => CheckStage::Payment,
            RequestStatus::ForFinalApproval => CheckStage::FinalCheck,
            default => null,
        };

        if ($stage === null || $action === 'decline') {
            return [];
        }

        $items = CheckItem::for($stage, $request);
        $results = $request->checkResults($stage);
        $rejected = array_filter($items, fn (CheckItem $item): bool => ($results[$item->value] ?? null) === CheckResult::Rejected);

        if ($action === 'return') {
            return $stage === CheckStage::Evaluation && $rejected === []
                ? ['Reject at least one part of the checklist, so the requester knows what to fix.']
                : [];
        }

        $unchecked = array_filter($items, fn (CheckItem $item): bool => ! isset($results[$item->value]));
        $blockers = [];

        if ($unchecked !== []) {
            $blockers[] = 'Confirm every part of the checklist. Not checked yet: '.$this->partNames($unchecked).'.';
        }

        if ($rejected !== []) {
            $blockers[] = 'Rejected: '.$this->partNames($rejected).'. Confirm '.(count($rejected) > 1 ? 'them' : 'it').' to approve, or '
                .($stage === CheckStage::FinalCheck ? 'decline' : 'return or decline').' the request.';
        }

        return $blockers;
    }

    /**
     * @param  array<int, CheckItem>  $parts
     */
    private function partNames(array $parts): string
    {
        return implode(', ', array_map(fn (CheckItem $item): string => $item->label(), $parts));
    }

    /**
     * @param  list<string>  $reasons
     */
    private function block(string $action, array $reasons): void
    {
        $this->blockedAction = $action;
        $this->blockers = $reasons;
        Flux::modal('decision-blocked')->show();
    }

    private function representative(int $id): RequestRepresentative
    {
        return $this->credentialRequest->representatives()->findOrFail($id);
    }
}
