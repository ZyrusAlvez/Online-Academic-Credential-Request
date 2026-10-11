<?php

namespace App\Livewire\Concerns;

use App\Models\CredentialRequest;
use App\Models\RequestStep;
use App\Services\RequestWorkflow;
use Flux\Flux;

/**
 * Sign / hold / lift-hold actions on a request's current step. The using component must
 * expose a `CredentialRequest $credentialRequest` property.
 *
 * @property CredentialRequest $credentialRequest
 */
trait ActsOnSteps
{
    use RunsTransitions;

    public string $signRemarks = '';

    public string $holdRemarks = '';

    public function signStep(RequestWorkflow $workflow): void
    {
        $step = $this->actionableStep();
        $this->validate(['signRemarks' => ['nullable', 'string', 'max:500']]);

        $this->attempt(fn () => $workflow->sign($step, $this->actor(), $this->signRemarks), "Signed for {$step->department->name}.");
        $this->reset('signRemarks');
    }

    public function holdStep(RequestWorkflow $workflow): void
    {
        $step = $this->actionableStep();
        $this->validate(['holdRemarks' => ['required', 'string', 'max:500']], attributes: ['holdRemarks' => 'reason']);

        $this->attempt(fn () => $workflow->hold($step, $this->actor(), $this->holdRemarks), 'Request put on hold. The student can see your reason.');
        Flux::modal('hold-step')->close();
        $this->reset('holdRemarks');
    }

    public function resumeStep(RequestWorkflow $workflow): void
    {
        $step = $this->actionableStep();

        $this->attempt(fn () => $workflow->resume($step, $this->actor()), 'Hold lifted.');
    }

    protected function actionableStep(): RequestStep
    {
        $step = $this->credentialRequest->currentStep()->with(['department', 'credentialRequest'])->first();

        abort_if($step === null, 403, 'There is no step waiting for a signature.');
        $this->authorize('act', $step);

        return $step;
    }
}
