<?php

namespace App\Livewire\Admin;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Queries\RegistrarRequestFilter;
use App\Reports\PerformanceMetrics;
use App\Reports\ReportFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Admin's performance dashboard (spec 07, REQ-18-08). Its links open Records.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** Period of the performance indicators (REQ-07-04); this month by default. */
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        $this->from = $this->from !== '' ? $this->from : now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to !== '' ? $this->to : now()->endOfMonth()->format('Y-m-d');
    }

    public function render(): View
    {
        /** @var array<string, int> $byStatus */
        $byStatus = CredentialRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $count = fn (RequestStatus ...$statuses): int => array_sum(array_map(fn (RequestStatus $s) => (int) ($byStatus[$s->value] ?? 0), $statuses));

        $period = new ReportFilter($this->from, $this->to);

        // Every tile opens Records with the matching filter (REQ-18-08).
        return view('livewire.admin.dashboard', [
            'tiles' => [
                ['label' => __('Needs the Registrar'), 'value' => $count(...RegistrarRequestFilter::needsRegistrar()), 'href' => route('records', ['status' => RegistrarRequestFilter::NEEDS_REGISTRAR])],
                ['label' => __('Pending compliance'), 'value' => $count(...RegistrarRequestFilter::waitingForRequester()), 'href' => route('records', ['status' => RegistrarRequestFilter::WAITING_FOR_REQUESTER])],
                ['label' => __('Awaiting payment'), 'value' => $count(RequestStatus::AwaitingPayment), 'href' => route('records', ['status' => RequestStatus::AwaitingPayment->value])],
                ['label' => __('Being processed'), 'value' => $count(...RequestStatus::processing()), 'href' => route('records', ['status' => RegistrarRequestFilter::PROCESSING])],
                ['label' => __('Ready for release'), 'value' => $count(RequestStatus::ReadyForRelease), 'href' => route('records', ['status' => RequestStatus::ReadyForRelease->value])],
            ],
            'overdueCount' => CredentialRequest::overdue()->count(),
            'metrics' => PerformanceMetrics::for($period),
            'reportsUrl' => route('admin.reports', ['report' => 'monthly', ...$period->toArray()]),
            'waitingByOffice' => $this->waitingByOffice(),
            'mostOverdue' => CredentialRequest::overdue()
                ->with(['credentialType:id,name', 'openVisit.department:id,name', 'openVisit.fromDepartment:id,name'])
                ->orderBy('effective_due_at')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Offices holding documents now, largest first. A forwarded document not yet received counts
     * at the office that forwarded it (REQ-17-15, 18).
     *
     * @return Collection<int, array{id: int, name: string, waiting: int, inTransit: int}>
     */
    private function waitingByOffice(): Collection
    {
        [$holder, $bindings] = OfficeVisit::holderSql();

        $rows = OfficeVisit::query()
            ->join('credential_requests', 'credential_requests.id', '=', 'office_visits.credential_request_id')
            ->whereIn('credential_requests.status', RequestStatus::processing())
            ->whereIn('office_visits.status', VisitStatus::open())
            ->selectRaw("{$holder} as holder_id", $bindings)
            ->selectRaw('count(*) as waiting')
            ->selectRaw("sum(office_visits.status = 'in_transit') as in_transit")
            ->groupBy('holder_id')
            ->get();

        $names = Department::whereIn('id', $rows->map(fn ($row) => (int) $row->getAttribute('holder_id')))->pluck('name', 'id');

        return $rows
            ->map(fn ($row) => [
                'id' => (int) $row->getAttribute('holder_id'),
                'name' => (string) ($names[(int) $row->getAttribute('holder_id')] ?? ''),
                'waiting' => (int) $row->getAttribute('waiting'),
                'inTransit' => (int) $row->getAttribute('in_transit'),
            ])
            ->sortBy([['waiting', 'desc'], ['name', 'asc']])
            ->values();
    }
}
