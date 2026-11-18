<?php

namespace App\Livewire\Registrar;

use App\Enums\RegistrarQueue;
use App\Exceptions\InvalidTransition;
use App\Livewire\Concerns\RunsTransitions;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Queries\StageQueue;
use App\Services\RequestWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One of the registrar's stage queues (REQ-16-02..06, REQ-32-02); the route chooses which.
 */
class Queue extends Component
{
    use RunsTransitions;
    use WithPagination;

    #[Locked]
    public RegistrarQueue $stage;

    /** Initial Evaluation and Payment Validation: show the requests waiting for the requester instead. */
    #[Url(except: false)]
    public bool $waiting = false;

    /** In Progress and Outgoing: the office holding the document. */
    #[Url(except: '')]
    public string $office = '';

    /** Every queue: tracking number or requester name (REQ-16-25). */
    #[Url(except: '')]
    public string $search = '';

    /**
     * Outgoing: the requests ticked for Forward (REQ-32-03).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    /**
     * @param  RegistrarQueue|string  $queue  the route's default, bound to the enum by Livewire
     */
    public function mount(RegistrarQueue|string $queue): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        $this->stage = $queue instanceof RegistrarQueue ? $queue : RegistrarQueue::from($queue);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['waiting', 'office', 'search'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function updatedPaginators(): void
    {
        $this->selected = [];
    }

    /**
     * Outgoing: bring one office's finished paper to its next office, or a returned one back
     * (REQ-32-03, 08).
     */
    public function forward(int $requestId, RequestWorkflow $workflow): void
    {
        abort_unless($this->stage === RegistrarQueue::Outgoing, 404);

        $visit = $this->openVisit($requestId);
        abort_if($visit === null, 404);
        $this->authorize('forward', $visit);

        if ($this->attempt(fn () => $workflow->forwardDocument($visit, $this->actor()), 'Forwarded. The next office confirms when it arrives.')) {
            $this->selected = array_values(array_diff(array_map(strval(...), $this->selected), [(string) $requestId]));
        }
    }

    /**
     * Outgoing: forward every ticked paper still waiting to be collected. Each is its own
     * transition, so one a colleague already forwarded is only counted (REQ-32-03).
     */
    public function forwardSelected(RequestWorkflow $workflow): void
    {
        abort_unless($this->stage === RegistrarQueue::Outgoing, 404);

        $this->validate([
            'selected' => ['required', 'array', 'max:100'],
            'selected.*' => ['integer'],
        ], ['selected.required' => 'Tick at least one document.']);

        $done = 0;

        foreach (array_map(intval(...), $this->selected) as $requestId) {
            $visit = $this->openVisit($requestId);

            if ($visit === null || Gate::denies('forward', $visit)) {
                continue;
            }

            try {
                $workflow->forwardDocument($visit, $this->actor());
                $done++;
            } catch (InvalidTransition) {
                // A colleague got there first: counted as not forwarded.
            }
        }

        $skipped = count($this->selected) - $done;
        $this->selected = [];

        $message = trans_choice('Forwarded :count document.|Forwarded :count documents.', $done);

        if ($skipped > 0) {
            $message .= ' '.trans_choice(':count not forwarded: already forwarded, or not ready.|:count not forwarded: already forwarded, or not ready.', $skipped);
        }

        $this->dispatch('notify', message: $message);
    }

    /**
     * The header checkbox: tick every row on the page, or clear them when all are ticked. The ids
     * only choose what to tick; forwardSelected() checks each again.
     *
     * @param  list<int|string>  $ids
     */
    public function togglePage(array $ids): void
    {
        $ids = array_slice(array_values(array_map(strval(...), array_filter($ids, fn ($id): bool => is_int($id) || ctype_digit((string) $id)))), 0, 100);
        $selected = array_map(strval(...), $this->selected);

        $this->selected = array_diff($ids, $selected) === []
            ? array_values(array_diff($selected, $ids))
            : array_values(array_unique([...$selected, ...$ids]));
    }

    public function render(): View
    {
        $stage = $this->stage;
        $hasWaitingTab = $stage->hasWaitingTab();
        $waiting = $hasWaitingTab && $this->waiting;
        $office = $stage->tracksOffices() ? $this->office : '';
        $requests = StageQueue::for($stage, $waiting, $office, $this->search)->paginate(20);

        return view('livewire.registrar.queue', [
            'queue' => $stage,
            'requests' => $requests,
            'hasWaitingTab' => $hasWaitingTab,
            'showWaiting' => $waiting,
            'waitingCount' => $hasWaitingTab ? StageQueue::for($stage, true)->count() : 0,
            'offices' => $stage->tracksOffices() ? Department::active()->orderBy('name')->get(['id', 'name']) : collect(),
            // The working page under this queue, so the sidebar keeps it current (REQ-16-26).
            'linkRoute' => $stage->showRouteName(),
            'pageIds' => $stage === RegistrarQueue::Outgoing ? $requests->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all() : [],
        ])->title(__($stage->label()));
    }

    private function openVisit(int $requestId): ?OfficeVisit
    {
        return CredentialRequest::find($requestId)?->openVisit()->with(['department', 'step', 'credentialRequest'])->first();
    }
}
