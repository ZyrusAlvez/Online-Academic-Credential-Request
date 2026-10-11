<?php

namespace App\Livewire\Registrar;

use App\Enums\RequestStatus;
use App\Enums\StepStatus;
use App\Models\CredentialRequest;
use App\Models\Department;
use App\Models\RequestStep;
use App\Queries\StepQueue;
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

        $avgHours = CredentialRequest::query()
            ->where('ready_at', '>=', now()->subDays(30))
            ->whereNotNull('routed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, routed_at, ready_at)'));

        return view('livewire.registrar.dashboard', [
            'tiles' => [
                ['label' => __('Needs review'), 'value' => $count(RequestStatus::PaymentReview, RequestStatus::RegistrarReview), 'href' => route('registrar.requests', ['status' => 'review'])],
                ['label' => __('Awaiting payment'), 'value' => $count(RequestStatus::AwaitingPayment), 'href' => route('registrar.requests', ['status' => RequestStatus::AwaitingPayment->value])],
                ['label' => __('In progress'), 'value' => $count(RequestStatus::InProgress), 'href' => route('registrar.requests', ['status' => RequestStatus::InProgress->value])],
                ['label' => __('Ready for release'), 'value' => $count(RequestStatus::ReadyForRelease), 'href' => route('registrar.requests', ['status' => RequestStatus::ReadyForRelease->value])],
            ],
            'overdueCount' => CredentialRequest::overdue()->count(),
            'submittedThisMonth' => CredentialRequest::where('created_at', '>=', now()->startOfMonth())->count(),
            'releasedThisMonth' => CredentialRequest::where('released_at', '>=', now()->startOfMonth())->count(),
            'avgTurnaroundDays' => $avgHours === null ? null : round(((float) $avgHours) / 24, 1),
            'waitingByOffice' => $this->waitingByOffice(),
            'mostOverdue' => CredentialRequest::overdue()
                ->with(['credentialType:id,name', 'currentStep.department:id,name'])
                ->orderBy('due_at')
                ->limit(5)
                ->get(),
            'office' => $office,
            'officeQueue' => $office
                ? StepQueue::waiting($office->id)->get()->concat(StepQueue::waiting($office->id, StepStatus::OnHold)->get())
                : collect(),
        ]);
    }

    /**
     * Offices currently holding in-progress requests, largest first.
     *
     * @return Collection<int, array{id: int, name: string, waiting: int, onHold: int}>
     */
    private function waitingByOffice(): Collection
    {
        $rows = RequestStep::query()
            ->join('credential_requests', 'credential_requests.id', '=', 'request_steps.credential_request_id')
            ->join('departments', 'departments.id', '=', 'request_steps.department_id')
            ->where('credential_requests.status', RequestStatus::InProgress)
            ->whereIn('request_steps.status', [StepStatus::Pending, StepStatus::OnHold])
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('departments.name')
            ->get([
                'departments.id',
                'departments.name',
                DB::raw('count(*) as waiting'),
                DB::raw("sum(request_steps.status = 'on_hold') as on_hold"),
            ]);

        return $rows->map(fn ($row) => [
            'id' => (int) $row->getAttribute('id'),
            'name' => (string) $row->getAttribute('name'),
            'waiting' => (int) $row->getAttribute('waiting'),
            'onHold' => (int) $row->getAttribute('on_hold'),
        ]);
    }
}
