<?php

namespace App\Livewire\Registrar\Records;

use App\Models\CredentialRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One request's record, read-only: details, route, office visits and ledger (REQ-16-07).
 */
class Show extends Component
{
    #[Locked]
    public CredentialRequest $credentialRequest;

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('review', $credentialRequest);

        $this->credentialRequest = $credentialRequest;
    }

    public function render(): View
    {
        $request = $this->credentialRequest->load([
            'student', 'identityDocument', 'credentialType', 'college', 'openVisit.department',
            'paymentProofs.reviewer', 'deficiencies', 'representatives.documents', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.doer', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ]);

        return view('livewire.registrar.records.show', ['request' => $request])->title($request->reference_no);
    }
}
