<?php

namespace App\Livewire\Admin;

use App\Models\CredentialRequest;
use App\Reports\DelayReport;
use App\Reports\DepartmentReport;
use App\Reports\PerformanceMetrics;
use App\Reports\ReportFilter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Admin's dashboard: which office takes the longest and why requests are late (spec 34).
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    /** The period of the indicators; this month by default (REQ-34-01). */
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
        $period = new ReportFilter($this->from, $this->to);

        return view('livewire.admin.dashboard', [
            'metrics' => PerformanceMetrics::for($period),
            'offices' => DepartmentReport::offices($period),
            'credentials' => PerformanceMetrics::byCredential($period),
            'causes' => DelayReport::causeCounts($period),
            // The period the drill-down links carry to Reports (REQ-34-01..03, 09).
            'period' => $period->toArray(),
        ]);
    }
}
