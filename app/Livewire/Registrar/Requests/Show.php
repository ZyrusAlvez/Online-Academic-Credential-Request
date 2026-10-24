<?php

namespace App\Livewire\Registrar\Requests;

use App\Actions\Identity\ReviewIdentityDocument;
use App\Enums\PaymentStatus;
use App\Enums\RecipientType;
use App\Enums\ReviewStatus;
use App\Livewire\Concerns\ActsOnVisits;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\IdentityDocument;
use App\Models\RequestDeficiency;
use App\Models\RequestRepresentative;
use App\Services\RequestWorkflow;
use App\Support\ReturnOffices;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The registrar's control of one request: evaluation, compliance, payment and acceptance
 * (spec 04), the Office of the Registrar's routing steps (spec 05), representatives and release
 * (spec 11).
 */
class Show extends Component
{
    use ActsOnVisits;
    use TrimsInput;

    #[Locked]
    public CredentialRequest $credentialRequest;

    /** @var list<string> Requirements ticked as missing. */
    public array $missing = [];

    public string $otherMissing = '';

    public string $evaluationRemarks = '';

    public string $actionMessage = '';

    public string $declineReason = '';

    #[Locked]
    public ?int $resolvingId = null;

    public string $resolution = '';

    public string $compliedNotes = '';

    public string $officialReceiptNo = '';

    public string $paymentRemarks = '';

    public string $rejectRemarks = '';

    public int|string|null $collegeId = null;

    public string $cancelReason = '';

    public string $bypassReason = '';

    public string $identityRemarks = '';

    #[Locked]
    public ?int $rejectingRepresentativeId = null;

    public string $representativeRemarks = '';

    public string $recipientType = 'owner';

    public int|string|null $recipientRepresentativeId = null;

    public string $recipientName = '';

    public string $idPresented = '';

    public bool $identityChecked = false;

    public bool $acknowledged = false;

    public string $releaseRemarks = '';

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('review', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
        $this->collegeId = $credentialRequest->college_id;
        $this->recipientName = $credentialRequest->student()->firstOrFail()->name;
    }

    // ── Evaluation (spec 04) ───────────────────────────────────────────────────────────────

    public function startEvaluation(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);

        $this->attempt(fn () => $workflow->startEvaluation($this->credentialRequest, $this->actor()), 'Evaluation started.');
    }

    public function completeEvaluation(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['evaluationRemarks' => ['nullable', 'string', 'max:500']]);

        $this->attempt(
            fn () => $workflow->completeEvaluation($this->credentialRequest, $this->actor(), $this->evaluationRemarks),
            $this->credentialRequest->requires_payment ? 'Requirements complete. Payment instructions sent to the requester.' : 'Requirements complete. Request accepted for processing.',
        );
        $this->reset('evaluationRemarks');
    }

    public function markIncomplete(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate([
            'missing' => ['array'],
            'missing.*' => ['string', 'max:255'],
            'otherMissing' => ['nullable', 'string', 'max:255'],
            'evaluationRemarks' => ['nullable', 'string', 'max:500'],
        ]);

        $items = [...$this->missing, ...(filled($this->otherMissing) ? [$this->otherMissing] : [])];

        if ($items === []) {
            throw ValidationException::withMessages(['otherMissing' => 'Tick or write at least one missing requirement.']);
        }

        if ($this->attempt(fn () => $workflow->requestCompliance($this->credentialRequest, $this->actor(), $items, $this->evaluationRemarks), 'The requester was told what to comply with.')) {
            Flux::modal('mark-incomplete')->close();
            $this->reset('missing', 'otherMissing', 'evaluationRemarks');
        }
    }

    public function confirmResolve(int $deficiencyId): void
    {
        $this->deficiency($deficiencyId);
        $this->resolvingId = $deficiencyId;
        $this->reset('resolution');
        $this->resetValidation();
        Flux::modal('resolve-deficiency')->show();
    }

    public function resolveDeficiency(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['resolution' => ['required', 'string', 'max:500']], attributes: ['resolution' => 'what was received']);

        $deficiency = $this->deficiency((int) $this->resolvingId);

        if ($this->attempt(fn () => $workflow->resolveDeficiency($deficiency, $this->actor(), $this->resolution), 'Compliance recorded.')) {
            Flux::modal('resolve-deficiency')->close();
            $this->reset('resolvingId', 'resolution');
        }
    }

    public function requestAction(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['actionMessage' => ['required', 'string', 'max:1000']], attributes: ['actionMessage' => 'message']);

        if ($this->attempt(fn () => $workflow->requestCustomerAction($this->credentialRequest, $this->actor(), $this->actionMessage), 'The requester was asked to act.')) {
            Flux::modal('request-action')->close();
            $this->reset('actionMessage');
        }
    }

    public function customerComplied(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['compliedNotes' => ['nullable', 'string', 'max:500']]);

        $this->attempt(fn () => $workflow->customerResponded($this->credentialRequest, $this->actor(), $this->compliedNotes), 'Recorded. The request continues where it was.');
        $this->reset('compliedNotes');
    }

    public function decline(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['declineReason' => ['required', 'string', 'max:500']], attributes: ['declineReason' => 'reason']);

        if ($this->attempt(fn () => $workflow->decline($this->credentialRequest, $this->actor(), $this->declineReason), 'Request declined. The requester was notified.')) {
            Flux::modal('decline-request')->close();
            $this->reset('declineReason');
        }
    }

    public function verifyIdentity(int $documentId, ReviewIdentityDocument $review): void
    {
        $this->authorize('review', $this->credentialRequest);
        $document = $this->identityDocument($documentId);

        $review->handle($document, $this->actor(), verified: true);
        $this->dispatch('notify', message: 'Identity verified.');
    }

    public function rejectIdentity(int $documentId, ReviewIdentityDocument $review): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['identityRemarks' => ['required', 'string', 'max:500']], attributes: ['identityRemarks' => 'reason']);

        $review->handle($this->identityDocument($documentId), $this->actor(), verified: false, remarks: $this->identityRemarks);
        Flux::modal('reject-identity')->close();
        $this->reset('identityRemarks');
        $this->dispatch('notify', message: 'ID rejected. The requester was asked to upload another.');
    }

    public function changeCollege(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['collegeId' => ['required', 'integer', Rule::exists('departments', 'id')->where('type', 'college')->where('is_active', true)]]);

        $this->attempt(fn () => $workflow->changeCollege($this->credentialRequest, $this->actor(), Department::findOrFail((int) $this->collegeId)), 'College updated.');
    }

    // ── Payment and acceptance (spec 04) ───────────────────────────────────────────────────

    public function verifyPayment(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate([
            'officialReceiptNo' => ['required', 'string', 'max:50'],
            'paymentRemarks' => ['nullable', 'string', 'max:500'],
        ], attributes: ['officialReceiptNo' => 'official receipt number']);

        $this->attempt(
            fn () => $workflow->verifyPaymentAndAccept($this->credentialRequest, $this->actor(), $this->officialReceiptNo, $this->paymentRemarks),
            'Payment verified and request accepted. The processing period has started.',
        );
        $this->reset('officialReceiptNo', 'paymentRemarks');
    }

    public function rejectPayment(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['rejectRemarks' => ['required', 'string', 'max:500']], attributes: ['rejectRemarks' => 'remarks']);

        $this->attempt(fn () => $workflow->rejectPayment($this->credentialRequest, $this->actor(), $this->rejectRemarks), 'Payment rejected. The requester was asked for a valid proof.');
        Flux::modal('reject-payment')->close();
        $this->reset('rejectRemarks');
    }

    // ── Routing overrides (spec 05) ────────────────────────────────────────────────────────

    public function bypass(RequestWorkflow $workflow): void
    {
        $visit = $this->credentialRequest->openVisit()->with(['step', 'department', 'credentialRequest'])->firstOrFail();
        $this->authorize('bypass', $visit);
        $this->validate(['bypassReason' => ['required', 'string', 'max:500']], attributes: ['bypassReason' => 'reason']);

        if ($this->attempt(fn () => $workflow->bypassStep($this->credentialRequest, $this->actor(), $this->bypassReason), "{$visit->department->name} bypassed.")) {
            Flux::modal('bypass-step')->close();
            $this->reset('bypassReason');
        }
    }

    public function cancel(RequestWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->credentialRequest);
        $this->validate(['cancelReason' => ['required', 'string', 'max:500']], attributes: ['cancelReason' => 'reason']);

        $this->attempt(fn () => $workflow->cancel($this->credentialRequest, $this->actor(), $this->cancelReason), 'Request cancelled.');
        Flux::modal('cancel-request')->close();
        $this->reset('cancelReason');
    }

    // ── Representatives and release (spec 11) ──────────────────────────────────────────────

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
            'recipientName' => ['required', 'string', 'max:150'],
            'idPresented' => ['required', 'string', 'max:100'],
            'identityChecked' => ['accepted'],
            'acknowledged' => ['accepted'],
            'releaseRemarks' => ['nullable', 'string', 'max:500'],
        ], [
            'identityChecked.accepted' => "Confirm that you checked the recipient's identity and authority.",
            'acknowledged.accepted' => 'Confirm that the recipient acknowledged receipt.',
        ], ['recipientRepresentativeId' => 'representative', 'idPresented' => 'ID presented']);

        $this->attempt(fn () => $workflow->release($this->credentialRequest, $this->actor(), [
            'recipient_type' => RecipientType::from($this->recipientType),
            'representative_id' => filled($this->recipientRepresentativeId) ? (int) $this->recipientRepresentativeId : null,
            'recipient_name' => $this->recipientName,
            'id_presented' => $this->idPresented,
            'identity_checked' => $this->identityChecked,
            'acknowledged' => $this->acknowledged,
            'remarks' => $this->releaseRemarks,
        ]), 'Credential released and the release recorded.');
    }

    public function updatedRecipientRepresentativeId(): void
    {
        $representative = $this->credentialRequest->representatives()->find((int) $this->recipientRepresentativeId);

        if ($representative !== null) {
            $this->recipientName = $representative->full_name;
        }
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student.latestIdentityDocument', 'student.college', 'credentialType', 'college', 'evaluator',
            'openVisit.department', 'openVisit.step', 'openVisit.credentialRequest.visits',
            'paymentProofs.reviewer', 'deficiencies.resolver', 'representatives.documents', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ]);

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
            'returnOffices' => ReturnOffices::for($request),
            'verifiedRepresentatives' => $request->representatives->where('status', ReviewStatus::Verified)->values(),
        ])->title($request->reference_no);
    }

    private function deficiency(int $id): RequestDeficiency
    {
        return $this->credentialRequest->deficiencies()->findOrFail($id);
    }

    private function representative(int $id): RequestRepresentative
    {
        return $this->credentialRequest->representatives()->findOrFail($id);
    }

    private function identityDocument(int $id): IdentityDocument
    {
        return IdentityDocument::where('user_id', $this->credentialRequest->user_id)->findOrFail($id);
    }
}
