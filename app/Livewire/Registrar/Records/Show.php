<?php

namespace App\Livewire\Registrar\Records;

use App\Enums\OverrideStep;
use App\Enums\ReviewStatus;
use App\Livewire\Concerns\OverridesSteps;
use App\Models\CredentialRequest;
use App\Support\ApplicationHistory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One application's record, read-only, with a tab per credential: details, route, office visits
 * and ledger (REQ-16-07, REQ-27-08), for the Registrar and the Admin (REQ-18-06). The Admin's
 * view has the Override (REQ-35-01).
 */
class Show extends Component
{
    use OverridesSteps;

    /** The application's first credential. */
    #[Locked]
    public CredentialRequest $credentialRequest;

    /** The credential tab shown: its position in the application (REQ-27-08). */
    #[Url(as: 'credential', except: 1)]
    public int $tab = 1;

    public function mount(CredentialRequest $credentialRequest): void
    {
        $this->authorize('view', $credentialRequest);

        // A credential's own record opens the application on its tab (REQ-27-08).
        if (! $credentialRequest->isLead()) {
            $this->tab = $credentialRequest->item_no;
            $credentialRequest = $credentialRequest->application()->firstOrFail()->lead()->firstOrFail();
        }

        $this->credentialRequest = $credentialRequest;
    }

    public function render(): View
    {
        $relations = [
            'student', 'identityDocument', 'credentialType', 'college', 'evaluator', 'openVisit.department', 'openVisit.fromDepartment',
            'paymentProofs.reviewer', 'deficiencies', 'representatives.documents', 'release.releaser',
            'steps.department', 'visits.department', 'visits.step', 'visits.returnedTo', 'visits.receiver', 'visits.doer', 'visits.completer',
            'delayNotes.department', 'delayNotes.recorder', 'pauses',
            'events.department', 'events.actor', 'events.step',
        ];

        $credentials = CredentialRequest::query()
            ->where('application_id', $this->credentialRequest->application_id)
            ->with([...$relations, 'application'])
            ->orderBy('item_no')
            ->get();
        $lead = $credentials->first() ?? $this->credentialRequest->load($relations);
        $current = $credentials->firstWhere('item_no', $this->tab) ?? $lead;

        return view('livewire.registrar.records.show', [
            'request' => $current,
            'credentials' => $credentials,
            // The application's entries until acceptance, then the credential's own (REQ-27-08).
            'events' => ApplicationHistory::for($current, $lead->events, $current->events),
            // The step the Override dialog was opened for (REQ-35-13).
            'override' => OverrideStep::tryFrom((string) $this->overrideStep),
            'verifiedRepresentatives' => $current->representatives->where('status', ReviewStatus::Verified)->values(),
        ])->title($credentials->count() > 1 ? $lead->application->reference_no : $current->reference_no);
    }

    /** The credential on the tab shown, which the Override acts for (REQ-35-01). */
    protected function overrideTarget(): CredentialRequest
    {
        return CredentialRequest::query()
            ->where('application_id', $this->credentialRequest->application_id)
            ->where('item_no', $this->tab)
            ->first() ?? $this->credentialRequest->fresh() ?? $this->credentialRequest;
    }
}
