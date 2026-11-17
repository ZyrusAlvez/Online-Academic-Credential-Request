<?php

namespace App\Livewire\Department;

use App\Enums\DepartmentQueue;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\CredentialType;
use App\Models\OfficeVisit;
use App\Models\User;
use App\Queries\VisitQueue;
use App\Services\RequestWorkflow;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * One of a department office's lists: Incoming, To Validate, Outgoing or History; the route
 * chooses which (REQ-24-01..05, REQ-32-05).
 */
class Queue extends Component
{
    use WithPagination;

    #[Locked]
    public DepartmentQueue $list;

    /** Tracking number or requester name (REQ-24-04). */
    #[Url(except: '')]
    public string $search = '';

    /** A credential type's id, or '' for any (REQ-24-04). */
    #[Url(except: '')]
    public string $type = '';

    /** request, requester or since (REQ-24-04). */
    #[Url(except: 'since')]
    public string $sort = 'since';

    #[Url(except: '')]
    public string $direction = '';

    /**
     * Incoming: the visits ticked for Acknowledge receipt (REQ-24-05).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    /**
     * @param  DepartmentQueue|string  $queue  the route's default, bound to the enum by Livewire
     */
    public function mount(DepartmentQueue|string $queue): void
    {
        $this->list = $queue instanceof DepartmentQueue ? $queue : DepartmentQueue::from($queue);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function updatedPaginators(): void
    {
        $this->selected = [];
    }

    /** Click a header: sort by it, ascending first; again flips the direction (REQ-24-04). */
    public function sortBy(string $column): void
    {
        if (! in_array($column, VisitQueue::SORTS, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->currentDirection() === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->selected = [];
        $this->resetPage();
    }

    /**
     * Incoming: accept the receipt of every ticked document still on its way to this office
     * (REQ-24-05). Each is its own transition, so one a colleague already received is only counted.
     */
    public function acknowledge(RequestWorkflow $workflow): void
    {
        abort_unless($this->list === DepartmentQueue::Incoming, 404);

        $done = $this->eachSelected(VisitStatus::InTransit, 'act', fn (OfficeVisit $visit, User $actor) => $workflow->acceptReceipt($visit, $actor));

        $this->report($done,
            'Receipt acknowledged for :count document.|Receipt acknowledged for :count documents.',
            ':count not acknowledged: already received, or no longer sent here.|:count not acknowledged: already received, or no longer sent here.');
    }

    /**
     * Run a transition on each ticked visit of this office in the given state that the policy
     * allows. Each is its own transaction, so one a colleague already handled is only counted.
     *
     * @param  Closure(OfficeVisit, User): void  $transition
     * @return int how many ran
     */
    private function eachSelected(VisitStatus $status, string $ability, Closure $transition): int
    {
        $this->validate([
            'selected' => ['required', 'array', 'max:100'],
            'selected.*' => ['integer'],
        ], ['selected.required' => 'Tick at least one document.']);

        $actor = $this->actor();
        $visits = OfficeVisit::query()
            ->whereKey(array_map(intval(...), $this->selected))
            ->where('department_id', $actor->department_id)
            ->with(['department', 'step', 'credentialRequest'])
            ->get();

        $done = 0;

        foreach ($visits as $visit) {
            if ($visit->status !== $status || Gate::denies($ability, $visit)) {
                continue;
            }

            try {
                $transition($visit, $actor);
                $done++;
            } catch (InvalidTransition) {
                // A colleague got there first: counted as not done.
            }
        }

        return $done;
    }

    private function report(int $done, string $doneText, string $skippedText): void
    {
        $skipped = count($this->selected) - $done;
        $this->selected = [];

        $message = trans_choice($doneText, $done);

        if ($skipped > 0) {
            $message .= ' '.trans_choice($skippedText, $skipped);
        }

        $this->dispatch('notify', message: $message);
    }

    /**
     * The header checkbox: tick every row on the page, or clear them when all are ticked
     * (REQ-24-05). The ids only choose what to tick; acknowledge() checks each again.
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
        $departmentId = (int) $this->actor()->department_id;
        $typeId = ctype_digit($this->type) ? (int) $this->type : null;
        $visits = VisitQueue::for($this->list, $departmentId, $this->search, $typeId, $this->sort, $this->direction)->paginate(20);

        return view('livewire.department.queue', [
            'queue' => $this->list,
            'visits' => $visits,
            'department' => $this->actor()->department,
            'types' => CredentialType::query()->orderBy('name')->get(['id', 'name']),
            'sortColumn' => in_array($this->sort, VisitQueue::SORTS, true) ? $this->sort : 'since',
            'sortDirection' => $this->currentDirection(),
            'pageIds' => $visits->getCollection()->pluck('id')->map(fn ($id): string => (string) $id)->all(),
        ])->title(__($this->list->label()));
    }

    private function currentDirection(): string
    {
        return in_array($this->direction, ['asc', 'desc'], true) ? $this->direction : $this->list->defaultDirection();
    }

    private function actor(): User
    {
        /** @var User */
        return Auth::user();
    }
}
