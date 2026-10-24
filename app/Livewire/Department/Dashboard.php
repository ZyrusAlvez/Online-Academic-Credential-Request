<?php

namespace App\Livewire\Department;

use App\Enums\VisitStatus;
use App\Models\User;
use App\Queries\VisitQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The office queue: documents forwarded to the office, documents in the office, history
 * (REQ-05-02).
 */
#[Title('Office Queue')]
class Dashboard extends Component
{
    use WithPagination;

    public const TABS = ['incoming', 'in_office', 'history'];

    #[Url(except: 'incoming')]
    public string $tab = 'incoming';

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'incoming';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $departmentId = (int) $user->department_id;

        $visits = match ($this->tab) {
            'history' => VisitQueue::history($departmentId)->paginate(20),
            'in_office' => VisitQueue::open($departmentId, VisitStatus::Received)->paginate(20),
            default => VisitQueue::open($departmentId, VisitStatus::InTransit)->paginate(20),
        };

        return view('livewire.department.dashboard', [
            'department' => $user->department,
            'visits' => $visits,
            'incomingCount' => VisitQueue::open($departmentId, VisitStatus::InTransit)->count(),
            'inOfficeCount' => VisitQueue::open($departmentId, VisitStatus::Received)->count(),
        ]);
    }
}
