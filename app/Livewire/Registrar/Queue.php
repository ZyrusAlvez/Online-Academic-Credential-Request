<?php

namespace App\Livewire\Registrar;

use App\Enums\RegistrarQueue;
use App\Livewire\Concerns\RunsTransitions;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Queries\StageQueue;
use App\Services\RequestWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One of the registrar's stage queues (REQ-16-02..06); the route chooses which.
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

    /** In Progress: the office holding the document. */
    #[Url(except: '')]
    public string $office = '';

    /** Every queue but Release: tracking number or requester name (REQ-16-25). */
    #[Url(except: '')]
    public string $search = '';

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
        }
    }

    /**
     * In Progress: send a paper the Office of the Registrar holds on to the next office; the only
     * place registrars forward (REQ-17-12, 16).
     */
    public function forward(int $requestId, RequestWorkflow $workflow): void
    {
        abort_unless($this->stage === RegistrarQueue::InProgress, 404);

        $visit = CredentialRequest::findOrFail($requestId)->openVisit()->with(['department', 'step', 'credentialRequest'])->first();
        abort_if($visit === null, 404);
        $this->authorize('forward', $visit);

        $this->attempt(fn () => $workflow->forwardDocument($visit, $this->actor()), 'Forwarded. The next office confirms when it arrives.');
    }

    public function render(): View
    {
        $stage = $this->stage;
        $hasWaitingTab = $stage->hasWaitingTab();
        $waiting = $hasWaitingTab && $this->waiting;
        $office = $stage === RegistrarQueue::InProgress ? $this->office : '';
        $search = $stage->isSearchable() ? $this->search : '';

        return view('livewire.registrar.queue', [
            'queue' => $stage,
            'requests' => StageQueue::for($stage, $waiting, $office, $search)->paginate(20),
            'hasWaitingTab' => $hasWaitingTab,
            'showWaiting' => $waiting,
            'waitingCount' => $hasWaitingTab ? StageQueue::for($stage, true)->count() : 0,
            'offices' => $stage === RegistrarQueue::InProgress ? Department::active()->orderBy('name')->get(['id', 'name']) : collect(),
            'linkRoute' => 'registrar.requests.show',
        ])->title(__($stage->label()));
    }
}
