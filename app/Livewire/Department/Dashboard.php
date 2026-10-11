<?php

namespace App\Livewire\Department;

use App\Enums\StepStatus;
use App\Models\User;
use App\Queries\StepQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Signature Queue')]
class Dashboard extends Component
{
    use WithPagination;

    public const TABS = ['pending', 'on_hold', 'history'];

    #[Url(except: 'pending')]
    public string $tab = 'pending';

    public function updatedTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'pending';
        }

        $this->resetPage();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $departmentId = (int) $user->department_id;

        $steps = match ($this->tab) {
            'history' => StepQueue::history($departmentId)->paginate(20),
            'on_hold' => StepQueue::waiting($departmentId, StepStatus::OnHold)->paginate(20),
            default => StepQueue::waiting($departmentId)->paginate(20),
        };

        return view('livewire.department.dashboard', [
            'department' => $user->department,
            'steps' => $steps,
            'pendingCount' => StepQueue::waiting($departmentId)->count(),
            'onHoldCount' => StepQueue::waiting($departmentId, StepStatus::OnHold)->count(),
        ]);
    }
}
