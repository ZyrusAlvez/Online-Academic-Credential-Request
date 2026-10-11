<?php

namespace App\Livewire\Registrar\Requests;

use App\Enums\PaymentStatus;
use App\Livewire\Concerns\ActsOnSteps;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use ActsOnSteps;

    #[Locked]
    public CredentialRequest $credentialRequest;

    public string $remarks = '';

    public string $rejectRemarks = '';

    public string $cancelReason = '';

    public ?int $collegeId = null;

    public string $skipReason = '';

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

        $this->attempt(fn () => $workflow->approve($this->credentialRequest, $this->actor(), $this->remarks), 'Request approved and routed to the first office.');
        $this->reset('remarks');
    }

    public function rejectPayment(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['rejectRemarks' => ['required', 'string', 'max:500']], attributes: ['rejectRemarks' => 'remarks']);

        $this->attempt(fn () => $workflow->rejectPayment($this->credentialRequest, $this->actor(), $this->rejectRemarks), 'Payment rejected. The student was asked to upload a new receipt.');
        Flux::modal('reject-payment')->close();
        $this->reset('rejectRemarks');
    }

    public function changeCollege(RequestWorkflow $workflow): void
    {
        $this->authorize('review', $this->credentialRequest);
        $this->validate(['collegeId' => ['required', 'integer', Rule::exists('departments', 'id')->where('type', 'college')->where('is_active', true)]]);

        $this->attempt(fn () => $workflow->changeCollege($this->credentialRequest, $this->actor(), Department::findOrFail($this->collegeId)), 'College updated.');
    }

    public function cancel(RequestWorkflow $workflow): void
    {
        $this->authorize('cancel', $this->credentialRequest);
        $this->validate(['cancelReason' => ['required', 'string', 'max:500']], attributes: ['cancelReason' => 'reason']);

        $this->attempt(fn () => $workflow->cancel($this->credentialRequest, $this->actor(), $this->cancelReason), 'Request cancelled.');
        Flux::modal('cancel-request')->close();
        $this->reset('cancelReason');
    }

    public function skipStep(RequestWorkflow $workflow): void
    {
        $step = $this->credentialRequest->currentStep()->with(['department', 'credentialRequest'])->firstOrFail();
        $this->authorize('skip', $step);
        $this->validate(['skipReason' => ['required', 'string', 'max:500']], attributes: ['skipReason' => 'reason']);

        $this->attempt(fn () => $workflow->skip($step, $this->actor(), $this->skipReason), "{$step->department->name} skipped.");
        Flux::modal('skip-step')->close();
        $this->reset('skipReason');
    }

    public function release(RequestWorkflow $workflow): void
    {
        $this->authorize('release', $this->credentialRequest);

        $this->attempt(fn () => $workflow->release($this->credentialRequest, $this->actor()), 'Marked as released to the student.');
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'credentialType', 'college', 'currentStep.department', 'currentStep.credentialRequest',
            'paymentProofs.reviewer', 'steps.department', 'steps.actor',
        ]);

        return view('livewire.registrar.requests.show', [
            'request' => $request,
            'pendingProof' => $request->paymentProofs->firstWhere('status', PaymentStatus::Submitted),
            'colleges' => Department::active()->colleges()->orderBy('name')->get(['id', 'name']),
        ])->title($request->reference_no);
    }
}
