<?php

namespace App\Livewire\Registrar\Reports;

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
 * The client's ten reports with shared filters and CSV export (REQ-07-13..20).
 */
#[Title('Reports')]
class Index extends Component
{
    /** Rows shown on the page; the CSV has them all. */
    public const PAGE_ROWS = 200;

    #[Url]
    public string $report = 'daily';

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

        $this->from = $this->from !== '' ? $this->from : now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to !== '' ? $this->to : now()->endOfMonth()->format('Y-m-d');
    }

    public function render(): View
    {
        $kind = ReportKind::tryFrom($this->report) ?? ReportKind::Daily;
        $current = $kind->report();
        $filter = $this->filter();
        $rows = $current->rows($filter);
        $rows = $rows instanceof LazyCollection || $rows instanceof Collection ? $rows : collect($rows);

        return view('livewire.registrar.reports.index', [
            'kinds' => ReportKind::cases(),
            'kind' => $kind,
            // Not "report": the public $report property (the slug) would shadow it in the view.
            'current' => $current,
            'rows' => $rows->take(self::PAGE_ROWS + 1)->values()->all(),
            'types' => CredentialType::orderBy('name')->get(['id', 'name']),
            'evaluators' => User::where('role', Role::Registrar)->orderBy('first_name')->get(),
            'offices' => Department::orderBy('name')->get(['id', 'name']),
            'exportUrl' => route('registrar.reports.export', ['report' => $kind->value, ...$filter->toArray()]),
        ]);
    }

    private function filter(): ReportFilter
    {
        return new ReportFilter($this->from, $this->to, $this->type, $this->evaluator, $this->office);
    }
}
