<?php

namespace App\Livewire\Registrar;

use App\Enums\RequestStatus;
use App\Enums\VisitStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\OfficeVisit;
use App\Queries\RegistrarRequestFilter;
use App\Queries\VisitQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Registrar Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);
    }

    public function render(): View
    {
        $office = Department::where('code', Department::REGISTRAR_CODE)->first();

        /** @var array<string, int> $byStatus */
        $byStatus = CredentialRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $count = fn (RequestStatus ...$statuses): int => array_sum(array_map(fn (RequestStatus $s) => (int) ($byStatus[$s->value] ?? 0), $statuses));

        $avgDays = CredentialRequest::query()
            ->where('released_at', '>=', now()->subDays(30))
            ->whereNotNull('processing_working_days')
            ->avg('processing_working_days');

        return view('livewire.registrar.dashboard', [
            'tiles' => [
                ['label' => __('Needs the Registrar'), 'value' => $count(...RegistrarRequestFilter::needsRegistrar()), 'href' => route('registrar.requests', ['status' => 'review'])],
                ['label' => __('Pending compliance'), 'value' => $count(RequestStatus::PendingCompliance, RequestStatus::ReturnedForCustomerAction), 'href' => route('registrar.requests', ['status' => RequestStatus::PendingCompliance->value])],
                ['label' => __('Awaiting payment'), 'value' => $count(RequestStatus::AwaitingPayment), 'href' => route('registrar.requests', ['status' => RequestStatus::AwaitingPayment->value])],
                ['label' => __('Being processed'), 'value' => $count(...RequestStatus::processing()), 'href' => route('registrar.requests', ['status' => 'processing'])],
                ['label' => __('Ready for release'), 'value' => $count(RequestStatus::ReadyForRelease), 'href' => route('registrar.requests', ['status' => RequestStatus::ReadyForRelease->value])],
            ],
            'overdueCount' => CredentialRequest::overdue()->count(),
            'submittedThisMonth' => CredentialRequest::where('submitted_at', '>=', now()->startOfMonth())->count(),
            'releasedThisMonth' => CredentialRequest::where('released_at', '>=', now()->startOfMonth())->count(),
            'avgProcessingDays' => $avgDays === null ? null : round((float) $avgDays, 1),
            'waitingByOffice' => $this->waitingByOffice(),
            'mostOverdue' => CredentialRequest::overdue()
                ->with(['credentialType:id,name', 'openVisit.department:id,name'])
                ->orderBy('effective_due_at')
                ->limit(5)
                ->get(),
            'office' => $office,
            'officeQueue' => $office
                ? VisitQueue::open($office->id, VisitStatus::InTransit)->get()->concat(VisitQueue::open($office->id, VisitStatus::Received)->get())
                : collect(),
        ]);
    }

    /**
     * Offices holding documents now (forwarded or received), largest first.
     *
     * @return Collection<int, array{id: int, name: string, waiting: int, inTransit: int}>
     */
    private function waitingByOffice(): Collection
    {
        $rows = OfficeVisit::query()
            ->join('credential_requests', 'credential_requests.id', '=', 'office_visits.credential_request_id')
            ->join('departments', 'departments.id', '=', 'office_visits.department_id')
            ->whereIn('credential_requests.status', RequestStatus::processing())
            ->whereIn('office_visits.status', [VisitStatus::InTransit, VisitStatus::Received])
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('departments.name')
            ->get([
                'departments.id',
                'departments.name',
                DB::raw('count(*) as waiting'),
                DB::raw("sum(office_visits.status = 'in_transit') as in_transit"),
            ]);

        return $rows->map(fn ($row) => [
            'id' => (int) $row->getAttribute('id'),
            'name' => (string) $row->getAttribute('name'),
            'waiting' => (int) $row->getAttribute('waiting'),
            'inTransit' => (int) $row->getAttribute('in_transit'),
        ]);
    }
}
