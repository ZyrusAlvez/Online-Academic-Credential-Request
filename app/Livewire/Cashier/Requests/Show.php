<?php

namespace App\Livewire\Cashier\Requests;

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Livewire\Concerns\ReturnsToList;
use App\Livewire\Concerns\RunsTransitions;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Services\RequestWorkflow;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The cashier's working page for an online payment: the proof, the payment details and the
 * decision of REQ-16-17 (REQ-25-05).
 */
class Show extends Component
{
    use ReturnsToList;
    use RunsTransitions;
    use TrimsInput;

    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $remarks = '';

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('view', $credentialRequest);
        $this->credentialRequest = $credentialRequest;
        $this->rememberList('cashier.payments');

        // Validated, returned or declined already: back to the list (REQ-25-05).
        if (! auth()->user()?->can('validatePayment', $credentialRequest)) {
            $this->backToList(__(':ref has no payment to validate.', ['ref' => $credentialRequest->reference_no]));
        }
    }

    public function approve(RequestWorkflow $workflow): void
    {
        $this->decide(['nullable', 'string', 'max:500'],
            fn () => $workflow->verifyPaymentAndAccept($this->credentialRequest, $this->actor(), $this->remarks),
            'Payment verified and request accepted. The processing period has started.');
    }

    public function returnRequest(RequestWorkflow $workflow): void
    {
        $this->decide(['required', 'string', 'max:500'],
            fn () => $workflow->rejectPayment($this->credentialRequest, $this->actor(), $this->remarks),
            'Proof of payment returned. The requester was asked for a valid one.');
    }

    public function decline(RequestWorkflow $workflow): void
    {
        $this->decide(['required', 'string', 'max:500'],
            fn () => $workflow->decline($this->credentialRequest, $this->actor(), $this->remarks),
            'Request declined. The requester was notified.');
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load(['student', 'credentialType', 'college', 'paymentProofs', 'events']);

        return view('livewire.cashier.requests.show', [
            'request' => $request,
            // One payment for every credential of the application (REQ-27-09).
            'credentials' => CredentialRequest::query()->where('application_id', $request->application_id)
                ->with('credentialType:id,name')->orderBy('item_no')->get(),
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            // What the requester wrote with the proof under review (REQ-04-20).
            'paymentNote' => (string) $request->events->last(fn ($event) => $event->type === RequestEventType::PaymentSubmitted)?->remarks,
        ])->title($request->reference_no);
    }

    /**
     * @param  list<string>  $rules  for the remarks
     */
    private function decide(array $rules, Closure $decision, string $success): void
    {
        // Walk-in payments are the registrar's (REQ-25-07). A payment validated meanwhile in another
        // tab is refused by the workflow, which keeps the remarks typed.
        abort_if($this->credentialRequest->isWalkIn(), 403);
        $this->validate(['remarks' => $rules], ['remarks.required' => 'Write your remarks; the requester sees them.']);

        if ($this->attempt($decision, $success)) {
            // The request has left Payment Validation: back to the list as it was left (REQ-25-05).
            $this->backToList($success);
        }
    }
}
