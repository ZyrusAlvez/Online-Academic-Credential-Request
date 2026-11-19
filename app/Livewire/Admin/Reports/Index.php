<?php

namespace App\Livewire\Admin\Reports;

use App\Enums\ReportKind;
use App\Enums\Role;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\Department;
use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The reports, each with its own filters (REQ-34-05, REQ-34-13, REQ-07-20), sortable by any column
 * and 50 rows per page (REQ-34-14). There is no CSV (REQ-16-08).
 */
#[Title('Reports')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 50;

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

    /** The chosen report's own filter, such as a cause or "pending" (REQ-34-13). */
    #[Url(except: '')]
    public string $show = '';

    /** The sorted column's index, or '' for the report's own order (REQ-34-14). */
    #[Url(except: '')]
    public string $sort = '';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        // A removed report's old link opens Office Performance (REQ-34-05).
        $this->report = (ReportKind::tryFrom($this->report) ?? ReportKind::Office)->value;
        $this->from = $this->from !== '' ? $this->from : now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to !== '' ? $this->to : now()->endOfMonth()->format('Y-m-d');
    }

    /** The own filter and the sort belong to one report (REQ-34-13, 14). */
    public function updatedReport(): void
    {
        $this->show = '';
        $this->sort = '';
        $this->direction = 'asc';
    }

    /** A new filter starts from the first page (REQ-34-14). */
    public function updated(string $property): void
    {
        if (in_array($property, ['report', 'from', 'to', 'type', 'evaluator', 'office', 'show'], true)) {
            $this->resetPage();
        }
    }

    /** Clicking a column sorts by it ascending, then descending (REQ-34-14). */
    public function sortBy(int $column): void
    {
        $this->direction = $this->sort === (string) $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = (string) $column;
        $this->resetPage();
    }

    public function render(): View
    {
        $kind = ReportKind::tryFrom($this->report) ?? ReportKind::Office;
        $current = $kind->report();
        $rows = $this->sorted(collect($current->rows($this->filter($current)))->values(), count($current->columns()));
        $page = max(1, min($this->getPage(), (int) ceil(max(1, $rows->count()) / self::PER_PAGE)));

        return view('livewire.admin.reports.index', [
            // The four listed reports, and an unlisted one while it is open (REQ-34-05).
            'kinds' => in_array($kind, ReportKind::listed(), true) ? ReportKind::listed() : [...ReportKind::listed(), $kind],
            'kind' => $kind,
            // Not "report": the public $report property (the slug) would shadow it in the view.
            'current' => $current,
            'rows' => new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page),
            'types' => CredentialType::active()->orderBy('name')->get(['id', 'name']),
            'evaluators' => User::where('role', Role::Registrar)->where('is_active', true)->orderBy('first_name')->get(),
            'offices' => Department::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * The rows sorted by the chosen column: numbers by value, other cells naturally and ignoring
     * case, empty cells last either way.
     *
     * @param  Collection<int, list<string|int|float|null>>  $rows
     * @return Collection<int, list<string|int|float|null>>
     */
    private function sorted(Collection $rows, int $columns): Collection
    {
        $column = ctype_digit($this->sort) ? (int) $this->sort : null;

        if ($column === null || $column >= $columns) {
            return $rows;
        }

        $descending = $this->direction === 'desc';

        return $rows->sort(function (array $a, array $b) use ($column, $descending): int {
            $x = $a[$column] ?? null;
            $y = $b[$column] ?? null;
            $xEmpty = $x === null || $x === '' || $x === '—';
            $yEmpty = $y === null || $y === '' || $y === '—';

            if ($xEmpty || $yEmpty) {
                return $xEmpty <=> $yEmpty;
            }

            $order = is_numeric($x) && is_numeric($y) ? (float) $x <=> (float) $y : strnatcasecmp((string) $x, (string) $y);

            return $descending ? -$order : $order;
        })->values();
    }

    /**
     * Only the filters the report shows narrow it (REQ-34-13).
     */
    private function filter(Report $report): ReportFilter
    {
        $uses = fn (string $name): bool => in_array($name, $report->filters(), true);

        return new ReportFilter(
            $this->from,
            $this->to,
            $uses('type') ? $this->type : '',
            $uses('evaluator') ? $this->evaluator : '',
            $uses('office') ? $this->office : '',
            array_key_exists($this->show, $report->shows()) ? $this->show : '',
        );
    }
}
