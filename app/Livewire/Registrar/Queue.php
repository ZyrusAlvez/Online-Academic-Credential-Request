<?php

namespace App\Livewire\Registrar;

use App\Enums\RegistrarQueue;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Queries\StageQueue;
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
    use WithPagination;

    #[Locked]
    public RegistrarQueue $stage;

    /** Initial Evaluation: show the requests waiting for the requester instead. */
    #[Url(except: false)]
    public bool $waiting = false;

    /** In Progress: the office holding the document. */
    #[Url(except: '')]
    public string $office = '';

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
        if (in_array($property, ['waiting', 'office'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $stage = $this->stage;
        $waiting = $stage === RegistrarQueue::Evaluation && $this->waiting;

        return view('livewire.registrar.queue', [
            'queue' => $stage,
            'requests' => StageQueue::for($stage, $waiting, $stage === RegistrarQueue::InProgress ? $this->office : '')->paginate(20),
            'showWaiting' => $waiting,
            'waitingCount' => $stage === RegistrarQueue::Evaluation ? StageQueue::for($stage, true)->count() : 0,
            'offices' => $stage === RegistrarQueue::InProgress ? Department::orderBy('name')->get(['id', 'name']) : collect(),
            // In Progress is for tracking: its rows open the read-only record (REQ-16-04).
            'linkRoute' => $stage === RegistrarQueue::InProgress ? 'registrar.records.show' : 'registrar.requests.show',
        ])->title(__($stage->label()));
    }
}
