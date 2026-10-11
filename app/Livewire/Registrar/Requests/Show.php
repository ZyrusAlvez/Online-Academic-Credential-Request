<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\PaymentStatus;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\RequestWorkflow;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $remarks = '';

    public string $rejectRemarks = '';

    public string $cancelReason = '';

    public ?int $collegeId = null;

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('review', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
        $this->collegeId = $credentialRequest->college_id;
    }

    public function approve(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['remarks' => ['nullable', 'string', 'max:500']]);

        $this->attempt(fn () => $workflow->approve($this->credentialRequest, $this->registrar(), $this->remarks), 'Request approved and routed to the first office.');
        $this->reset('remarks');
    }

    public function rejectPayment(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['rejectRemarks' => ['required', 'string', 'max:500']], attributes: ['rejectRemarks' => 'remarks']);

        $this->attempt(fn () => $workflow->rejectPayment($this->credentialRequest, $this->registrar(), $this->rejectRemarks), 'Payment rejected. The student was asked to upload a new receipt.');
        Flux::modal('reject-payment')->close();
        $this->reset('rejectRemarks');
    }

    public function changeCollege(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['collegeId' => ['required', 'integer', Rule::exists('departments', 'id')->where('type', 'college')->where('is_active', true)]]);

        $this->attempt(fn () => $workflow->changeCollege($this->credentialRequest, $this->registrar(), Department::findOrFail($this->collegeId)), 'College updated.');
    }

    public function cancel(RequestWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->credentialRequest);
        $this->validate(['cancelReason' => ['required', 'string', 'max:500']], attributes: ['cancelReason' => 'reason']);

        $this->attempt(fn () => $workflow->cancel($this->credentialRequest, $this->registrar(), $this->cancelReason), 'Request cancelled.');
        Flux::modal('cancel-request')->close();
        $this->reset('cancelReason');
    }

    /**
     * Run a workflow transition, reporting refused transitions as a toast instead of an error page.
     */
    private function attempt(Closure $transition, string $success): void
    {
        try {
            $transition();
            $this->dispatch('notify', message: $success);
        } catch (InvalidTransition $e) {
            $this->dispatch('notify', message: $e->getMessage());
        }
    }

    private function registrar(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'credentialType', 'college', 'currentStep.department', 'paymentProofs.reviewer', 'steps.department',
        ]);

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
        ])->title($request->reference_no);
    }
}
