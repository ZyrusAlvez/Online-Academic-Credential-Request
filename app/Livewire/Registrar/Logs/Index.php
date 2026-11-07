<?php

namespace App\Livewire\Registrar\Logs;

use App\Enums\ActivityCategory;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Queries\ActivityLogFilter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything everyone did, searchable by the registrar (spec 14).
 */
#[Title('Logs')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $office = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ActivityLog::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'category', 'office', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role', 'category', 'office', 'from', 'to');
        $this->resetPage();
    }

    public function render(): View
    {
        $filter = new ActivityLogFilter($this->search, $this->role, $this->category, $this->office, $this->from, $this->to);

        return view('livewire.registrar.logs.index', [
            'logs' => $filter->query()->with('department:id,name')->paginate(25),
            'roles' => Role::cases(),
            'categories' => ActivityCategory::cases(),
            'offices' => Department::active()->orderBy('type')->orderBy('name')->get(['id', 'name']),
            'filtered' => array_filter([$this->search, $this->role, $this->category, $this->office, $this->from, $this->to]) !== [],
        ]);
    }
}
