<?php

namespace App\Livewire\Concerns;

use App\Enums\DelayCategory;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Services\RequestWorkflow;
use Flux\Flux;
use Illuminate\Validation\Rule;

/**
 * Office actions on a request's open visit: accept receipt, approve and forward, return for
 * correction, record a delay (spec 05). The using component must expose a
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

        $this->attempt(fn () => $workflow->completeVisit($visit, $this->actor(), $this->completeRemarks), 'Done. The document was forwarded.');
        $this->reset('completeRemarks');
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

        if (! $this->actor()->can('review', $this->credentialRequest)) {
            abort_if($visit === null, 403);
            $this->authorize('act', $visit);
        }

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
