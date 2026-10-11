<?php

namespace App\Services;

use App\Enums\DepartmentType;
use App\Enums\RequestStatus;
use App\Enums\StudentStatus;
use App\Models\CredentialRequest;
use App\Models\CredentialType;
use App\Models\WorkflowStep;
use Illuminate\Support\Carbon;

/**
 * Predicts how many calendar days a request will take from routing to ready for release.
 *
 * The model is a linear regression trained offline by ml/train.py and exported to
 * resources/ml/turnaround-model.json with coefficients on the raw feature scale, so a
 * prediction is the intercept plus a weighted sum of the features. See spec 08.
 */
class TurnaroundPredictor
{
    /** Model inputs, in the order ml/train.py uses them. */
    public const FEATURES = [
        'processing_days',
        'requires_payment',
        'has_library',
        'has_finance',
        'has_student_affairs',
        'has_college',
        'copies',
        'is_alumni',
        'open_workload',
        'is_peak_month',
    ];

    /** @var array{version: string, intercept: float, coefficients: array<string, float>, peak_months: list<int>, metrics: array<string, float>, baseline_metrics: array<string, float>, algorithm: string, trained_on: string}|false|null */
    private array|false|null $model = null;

    public function __construct(private readonly ?string $path = null) {}

    public function isAvailable(): bool
    {
        return $this->model() !== false;
    }

    public function version(): ?string
    {
        return $this->model() ? $this->model()['version'] : null;
    }

    /**
     * @return array{version: string, intercept: float, coefficients: array<string, float>, peak_months: list<int>, metrics: array<string, float>, baseline_metrics: array<string, float>, algorithm: string, trained_on: string}|null
     */
    public function details(): ?array
    {
        return $this->model() ?: null;
    }

    /**
     * Inputs for a request profile, using only what is known when the request is routed.
     *
     * @return array<string, int>
     */
    public function features(
        CredentialType $type,
        int $copies,
        StudentStatus $studentStatus,
        Carbon $at,
        ?int $openWorkload = null,
    ): array {
        $steps = $type->steps()->with('department:id,code,type')->get();
        $includes = fn (string $code): int => (int) $steps->contains(fn (WorkflowStep $s) => $s->department?->code === $code);

        return [
            'processing_days' => (int) $type->processing_days,
            'requires_payment' => (int) $type->requires_payment,
            'has_library' => $includes('LIB'),
            'has_finance' => $includes('FIN'),
            'has_student_affairs' => $includes('SAS'),
            'has_college' => (int) $steps->contains(
                fn (WorkflowStep $s) => $s->is_student_college || $s->department?->type === DepartmentType::College,
            ),
            'copies' => $copies,
            'is_alumni' => (int) ($studentStatus === StudentStatus::Alumni),
            'open_workload' => $openWorkload ?? CredentialRequest::where('status', RequestStatus::InProgress)->count(),
            'is_peak_month' => (int) in_array($at->month, $this->peakMonths(), true),
        ];
    }

    /**
     * Predicted calendar days, never below zero. Null when no valid model is loaded.
     *
     * @param  array<string, int|float>  $features
     */
    public function predict(array $features): ?float
    {
        $model = $this->model();

        if ($model === false) {
            return null;
        }

        $days = $model['intercept'];
        foreach (self::FEATURES as $name) {
            $days += $model['coefficients'][$name] * (float) ($features[$name] ?? 0);
        }

        return round(max(0.0, $days), 2);
    }

    /** Ready date for a prediction: the routing time plus the predicted days. */
    public static function readyAt(Carbon $routedAt, float $days): Carbon
    {
        return $routedAt->copy()->addMinutes((int) round($days * 24 * 60));
    }

    /** The due date the workflow sets on routing (weekdays only), see REQ-07-01. */
    public static function dueAt(Carbon $routedAt, CredentialType $type): Carbon
    {
        return $routedAt->copy()->addWeekdays((int) $type->processing_days);
    }

    /**
     * @return list<int>
     */
    private function peakMonths(): array
    {
        return $this->model() ? $this->model()['peak_months'] : [];
    }

    /**
     * Load and validate the model file once. False when it is missing or malformed.
     *
     * @return array{version: string, intercept: float, coefficients: array<string, float>, peak_months: list<int>, metrics: array<string, float>, baseline_metrics: array<string, float>, algorithm: string, trained_on: string}|false
     */
    private function model(): array|false
    {
        if ($this->model !== null) {
            return $this->model;
        }

        $path = $this->path ?? resource_path('ml/turnaround-model.json');
        $json = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        $valid = is_array($json)
            && is_string($json['version'] ?? null)
            && is_numeric($json['intercept'] ?? null)
            && is_array($json['coefficients'] ?? null)
            && is_array($json['peak_months'] ?? null)
            && collect(self::FEATURES)->every(fn (string $f) => is_numeric($json['coefficients'][$f] ?? null));

        if (! $valid) {
            return $this->model = false;
        }

        return $this->model = [
            'version' => $json['version'],
            'intercept' => (float) $json['intercept'],
            'coefficients' => array_map('floatval', array_intersect_key($json['coefficients'], array_flip(self::FEATURES))),
            'peak_months' => array_values(array_map('intval', $json['peak_months'])),
            'metrics' => array_map('floatval', (array) ($json['metrics'] ?? [])),
            'baseline_metrics' => array_map('floatval', (array) ($json['baseline_metrics'] ?? [])),
            'algorithm' => (string) ($json['algorithm'] ?? 'Linear regression'),
            'trained_on' => (string) ($json['trained_on'] ?? 'unknown'),
        ];
    }
}
