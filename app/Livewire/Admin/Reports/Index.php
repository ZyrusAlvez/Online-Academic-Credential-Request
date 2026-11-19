<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\ReportKind;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Reports\ReportFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The four reports with shared filters (REQ-34-05, REQ-07-20). There is no CSV (REQ-16-08).
 */
#[Title('Reports')]
class Index extends Component
{
    /** Rows shown on the page; narrower filters show the rest. */
    public const PAGE_ROWS = 200;

    #[Url]
    public string $report = 'office';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $evaluator = '';

    #[Url(except: '')]
    public string $office = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        // A removed report's old link opens Office Performance (REQ-34-05).
        $this->report = (ReportKind::tryFrom($this->report) ?? ReportKind::Office)->value;
        $this->from = $this->from !== '' ? $this->from : now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to !== '' ? $this->to : now()->endOfMonth()->format('Y-m-d');
    }

    public function render(): View
    {
        $kind = ReportKind::tryFrom($this->report) ?? ReportKind::Office;
        $current = $kind->report();
        $filter = $this->filter();
        $rows = $current->rows($filter);
        $rows = $rows instanceof LazyCollection || $rows instanceof Collection ? $rows : collect($rows);

        return view('livewire.admin.reports.index', [
            'kinds' => ReportKind::cases(),
            'kind' => $kind,
            // Not "report": the public $report property (the slug) would shadow it in the view.
            'current' => $current,
            'rows' => $rows->take(self::PAGE_ROWS + 1)->values()->all(),
            'types' => CredentialType::active()->orderBy('name')->get(['id', 'name']),
            'evaluators' => User::where('role', Role::Registrar)->where('is_active', true)->orderBy('first_name')->get(),
            'offices' => Department::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function filter(): ReportFilter
    {
        return new ReportFilter($this->from, $this->to, $this->type, $this->evaluator, $this->office);
    }
}
