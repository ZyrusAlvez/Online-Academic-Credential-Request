<?php

namespace App\Livewire\Department\Requests;

use App\Livewire\Concerns\ActsOnSteps;
use App\Models\CredentialRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use ActsOnSteps;

    #[Locked]
    public CredentialRequest $credentialRequest;

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('view', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'credentialType', 'college', 'currentStep.department', 'currentStep.credentialRequest', 'steps.department', 'steps.actor', 'events.department', 'events.actor',
        ]);

        return view('livewire.department.requests.show', ['request' => $request])->title($request->reference_no);
    }
}
