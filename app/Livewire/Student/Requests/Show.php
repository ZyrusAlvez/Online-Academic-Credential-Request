<?php

namespace App\Livewire\Student\Requests;

use App\Enums\PaymentStatus;
use App\Enums\RequestEventType;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Models\User;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use TrimsInput;
    use WithFileUploads;

    #[Locked]
    public CredentialRequest $credentialRequest;

    /** @var UploadedFile|null */
    public $receipt = null;

    public string $paymentReference = '';

    public string $amountPaid = '';

    public string $cancelReason = '';

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('view', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
    }

    public function uploadReceipt(RequestWorkflow $workflow): void
    {
        $this->authorize('uploadPayment', $this->credentialRequest);

        $this->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'paymentReference' => ['nullable', 'string', 'max:100'],
            'amountPaid' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
        ], attributes: ['receipt' => 'proof of payment']);

        try {
            $workflow->submitPaymentProof($this->credentialRequest, $this->student(), $this->receipt, $this->paymentReference, $this->amountPaid);
        } catch (InvalidTransition $e) {
            $this->addError('receipt', $e->getMessage());

            return;
        }

        $this->reset('receipt', 'paymentReference', 'amountPaid');
        $this->dispatch('notify', message: 'Receipt uploaded. The Registrar will verify your payment.');
    }

    public function cancel(RequestWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->credentialRequest);

        $this->validate(['cancelReason' => ['nullable', 'string', 'max:500']]);

        try {
            $workflow->cancel($this->credentialRequest, $this->student(), $this->cancelReason);
        } catch (InvalidTransition $e) {
            Flux::modal('cancel-request')->close();
            $this->dispatch('notify', message: $e->getMessage());

            return;
        }

        Flux::modal('cancel-request')->close();
        $this->dispatch('notify', message: 'Your request was cancelled.');
    }

    private function student(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function render(): View
    {
        $this->credentialRequest->load([
            'credentialType', 'college', 'currentStep.department', 'paymentProofs', 'steps.department',
        ]);

        return view('livewire.student.requests.show', [
            'request' => $this->credentialRequest,
            'rejectedProof' => $this->credentialRequest->paymentProofs->firstWhere('status', PaymentStatus::Rejected),
            // Internal registrar bookkeeping is not part of the student's tracking history.
            'events' => $this->credentialRequest->events()->with('department')
                ->where('type', '!=', RequestEventType::PaymentAccepted)->get(),
        ])->title($this->credentialRequest->reference_no);
    }
}
