<?php

namespace App\Livewire\Department\Requests;

use App\Livewire\Concerns\ActsOnVisits;
use App\Livewire\Concerns\TrimsInput;
use App\Models\CredentialRequest;
use App\Support\ReturnOffices;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use ActsOnVisits;
    use TrimsInput;

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
            'student', 'credentialType', 'college',
            'openVisit.department', 'openVisit.step', 'openVisit.credentialRequest.visits',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.completer',
            'events.department', 'events.step',
        ]);

        return view('livewire.department.requests.show', [
            'request' => $request,
            'returnOffices' => ReturnOffices::for($request),
        ])->title($request->reference_no);
    }
}
