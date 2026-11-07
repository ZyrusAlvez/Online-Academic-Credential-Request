<?php

namespace App\Livewire\Concerns;

use App\Enums\DelayCategory;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Validation\Rule;

/**
 * Office actions on a request's open visit: accept receipt, mark done, forward, return for
 * correction, record a delay (specs 05, 17). The using component must expose a
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

    public string $delayCategory = '';

    public string $delayReason = '';

    public string $delayCorrectiveAction = '';

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
        if ($this->attempt(fn () => $workflow->completeVisit($visit, $this->actor(), $this->completeRemarks), 'Marked done. Forward the document when it leaves your office.')) {
            $this->reset('completeRemarks');
        }
    }

    /**
     * The paper leaves the office (REQ-17-07).
     */
    public function forwardDocument(RequestWorkflow $workflow): void
    {
        $visit = $this->actionableVisit();

        $this->attempt(fn () => $workflow->forwardDocument($visit, $this->actor()), 'Forwarded. The next office confirms when it arrives.');
    }

    public function returnVisit(RequestWorkflow $workflow): void
    {
        $visit = $this->actionableVisit();
        $this->validate([
            'returnTo' => ['required', 'integer', Rule::exists('departments', 'id')],
            'returnReason' => ['required', 'string', 'max:500'],
            'correctiveAction' => ['required', 'string', 'max:500'],
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

    public function recordDelay(RequestWorkflow $workflow): void
    {
        $visit = $this->credentialRequest->openVisit()->with(['department', 'step', 'credentialRequest'])->first();

        // Department offices only; the registrar does not record delay reasons (REQ-16-21).
        abort_if($visit === null || $this->actor()->hasRole(Role::Registrar), 403);
        $this->authorize('act', $visit);

        $this->validate([
            'delayCategory' => ['required', Rule::enum(DelayCategory::class)],
            'delayReason' => ['required', 'string', 'max:500'],
            'delayCorrectiveAction' => ['nullable', 'string', 'max:500'],
        ], attributes: ['delayCategory' => 'category', 'delayReason' => 'reason']);

        $recorded = $this->attempt(
            fn () => $workflow->recordDelay($this->credentialRequest, $this->actor(), DelayCategory::from($this->delayCategory), $this->delayReason, $this->delayCorrectiveAction),
            'Delay reason recorded.',
        );

        if ($recorded) {
            Flux::modal('record-delay')->close();
            $this->reset('delayCategory', 'delayReason', 'delayCorrectiveAction');
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
