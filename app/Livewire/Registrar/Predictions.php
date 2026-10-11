<?php

namespace App\Livewire\Registrar;

use App\Enums\RequestStatus;
use App\Enums\StudentStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Services\TurnaroundPredictor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Turnaround prediction console (spec 08): try a request profile, see requests predicted
 * to miss their due date, and compare predictions with actual turnaround.
 */
#[Title('Turnaround Prediction')]
class Predictions extends Component
{
    public ?int $credentialTypeId = null;

    public int $copies = 1;

    public string $studentStatus = 'alumni';

    public ?int $openWorkload = null;

    /** @var array{days: float, readyAt: string, dueAt: string, atRisk: bool, contributions: list<array{feature: string, label: string, value: int, days: float}>, intercept: float}|null */
    public ?array $result = null;

    /** Plain-language names for the model inputs. */
    public const LABELS = [
        'processing_days' => 'Processing days',
        'requires_payment' => 'Paid credential',
        'has_library' => 'Library step',
        'has_finance' => 'Cashier step',
        'has_student_affairs' => 'Student Affairs step',
        'has_college' => 'College step',
        'copies' => 'Copies',
        'is_alumni' => 'Alumni requester',
        'open_workload' => 'Open requests',
        'is_peak_month' => 'Peak month',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        $this->credentialTypeId = CredentialType::active()->orderBy('name')->value('id');
        $this->openWorkload = CredentialRequest::where('status', RequestStatus::InProgress)->count();
    }

    public function predict(TurnaroundPredictor $predictor): void
    {
        $this->authorize('viewAny', CredentialRequest::class);

        $this->validate([
            'credentialTypeId' => ['required', 'integer', Rule::exists(CredentialType::class, 'id')->where('is_active', true)],
            'copies' => ['required', 'integer', 'between:1,10'],
            'studentStatus' => ['required', Rule::enum(StudentStatus::class)],
            'openWorkload' => ['required', 'integer', 'between:0,100000'],
        ], attributes: [
            'credentialTypeId' => 'credential',
            'studentStatus' => 'student status',
            'openWorkload' => 'open requests',
        ]);

        $model = $predictor->details();
        if ($model === null) {
            $this->addError('credentialTypeId', __('The prediction model is not available.'));

            return;
        }

        $type = CredentialType::findOrFail($this->credentialTypeId);
        $now = now();
        $features = $predictor->features($type, $this->copies, StudentStatus::from($this->studentStatus), $now, (int) $this->openWorkload);
        $days = (float) $predictor->predict($features);
        $readyAt = TurnaroundPredictor::readyAt($now, $days);
        $dueAt = TurnaroundPredictor::dueAt($now, $type);

        $contributions = collect(TurnaroundPredictor::FEATURES)
            ->map(fn (string $f) => [
                'feature' => $f,
                'label' => self::LABELS[$f],
                'value' => $features[$f],
                'days' => round($model['coefficients'][$f] * $features[$f], 2),
            ])
            ->filter(fn (array $c) => $c['days'] != 0.0)
            ->sortByDesc(fn (array $c) => abs($c['days']))
            ->values()
            ->all();

        $this->result = [
            'days' => $days,
            'readyAt' => $readyAt->toIso8601String(),
            'dueAt' => $dueAt->toIso8601String(),
            'atRisk' => $readyAt->greaterThan($dueAt),
            'contributions' => $contributions,
            'intercept' => round($model['intercept'], 2),
        ];
    }

    public function render(TurnaroundPredictor $predictor): View
    {
        return view('livewire.registrar.predictions', [
            'model' => $predictor->details(),
            'types' => CredentialType::active()->orderBy('name')->get(['id', 'name', 'processing_days']),
            'atRisk' => CredentialRequest::query()
                ->where('status', RequestStatus::InProgress)
                ->whereHas('prediction', fn ($q) => $q->where('at_risk', true))
                ->with(['prediction', 'credentialType:id,name', 'currentStep.department:id,name'])
                ->get()
                ->sortBy(fn (CredentialRequest $r) => $r->prediction?->predicted_ready_at)
                ->values(),
            'completed' => $this->completed(),
        ]);
    }

    /**
     * Requests that reached ready for release and had a prediction: predicted vs actual days.
     *
     * @return Collection<int, array{reference: string, predicted: float, actual: float}>
     */
    private function completed(): Collection
    {
        return CredentialRequest::query()
            ->whereNotNull('ready_at')
            ->whereNotNull('routed_at')
            ->whereHas('prediction')
            ->with('prediction:id,credential_request_id,predicted_days')
            ->latest('ready_at')
            ->limit(200)
            ->get(['id', 'reference_no', 'routed_at', 'ready_at'])
            ->map(fn (CredentialRequest $r) => [
                'reference' => $r->reference_no,
                'predicted' => (float) $r->prediction?->predicted_days,
                'actual' => round($r->routed_at->diffInMinutes($r->ready_at) / 1440, 2),
            ]);
    }
}
