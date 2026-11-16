<?php

namespace App\Livewire\Concerns;

use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Validation\Rule;

/**
 * Office actions on a request's open visit: accept receipt, mark done, forward, return for
 * correction (specs 05, 17). No delay reasons (REQ-24-09). Registrars forward from In Progress (REQ-17-16). The using component must expose a
 * `CredentialRequest $credentialRequest` property.
 *
 * @property CredentialRequest $credentialRequest
 */
trait ActsOnVisits
{
    use RunsTransitions;

    public string $completeRemarks = '';

    public int|string|null $returnTo = null;

    public string $returnReason = '';

    public string $correctiveAction = '';

    public function acceptReceipt(RequestWorkflow $workflow): void
    {
        $visit = $this->actionableVisit();

        $this->attempt(fn () => $workflow->acceptReceipt($visit, $this->actor()), "Receipt confirmed at {$visit->department->name}.");
    }

    public function completeVisit(RequestWorkflow $workflow): void
    {
        $visit = $this->actionableVisit();
        $this->validate(['completeRemarks' => ['nullable', 'string', 'max:500']]);

        // A failed attempt keeps the remarks the staff member typed.
        // A failed attempt keeps the remarks the staff member typed.
        if ($this->attempt(fn () => $workflow->completeVisit($visit, $this->actor(), $this->completeRemarks), 'Marked done. Forward the document when it leaves your office.')) {
            $this->reset('completeRemarks');
        }
    }

    /**
     * A department office sends its done paper on (REQ-17-07). Registrars forward the Office of
     * the Registrar's papers from In Progress only (REQ-17-16).
     */
    public function forwardDocument(RequestWorkflow $workflow): void
    {
        abort_if($this->actor()->hasRole(Role::Registrar), 403);

        $visit = $this->actionableVisit();
        $this->authorize('forward', $visit);

        $this->attempt(fn () => $workflow->forwardDocument($visit, $this->actor()), 'Forwarded. The next office confirms when it arrives.');
    }

    public function returnVisit(RequestWorkflow $workflow): void
    {
        $visit = $this->actionableVisit();
        $this->validate([
            'returnTo' => ['required', 'integer', Rule::exists('departments', 'id')],
            'returnReason' => ['required', 'string', 'max:500'],
            'correctiveAction' => ['nullable', 'string', 'max:500'],
        ], attributes: ['returnTo' => 'office', 'returnReason' => 'reason']);

        $returned = $this->attempt(
            fn () => $workflow->returnVisit($visit, $this->actor(), Department::findOrFail((int) $this->returnTo), $this->returnReason, $this->correctiveAction),
            'Returned for correction. The office has been notified.',
        );

        if ($returned) {
            Flux::modal('return-visit')->close();
            $this->reset('returnTo', 'returnReason', 'correctiveAction');
        }
    }

    protected function actionableVisit(): OfficeVisit
    {
        $visit = $this->credentialRequest->openVisit()->with(['department', 'step', 'credentialRequest'])->first();

        abort_if($visit === null, 403, 'No office has the document right now.');
        $this->authorize('act', $visit);

        return $visit;
    }
}
