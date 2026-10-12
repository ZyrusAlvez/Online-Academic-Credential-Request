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
 *
 * @phpstan-type TurnaroundModel array{version: string, intercept: float, coefficients: array<string, float>, peak_months: list<int>, residual_quantiles: list<float>, metrics: array<string, float>, baseline_metrics: array<string, float>, algorithm: string, trained_on: string}
 * @phpstan-type Confidence array{low: float, high: float, lateProbability: float, confidence: float, atRisk: bool}
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

    /** @var TurnaroundModel|false|null */
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
     * @return TurnaroundModel|null
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

    /**
     * How sure the prediction is (REQ-08-11), from the model's cross-validation errors: an 80% likely
     * range of days, the probability the request is ready after its due date, and the confidence in
     * the at-risk call. Null when the model file has no error percentiles.
     *
     * @return Confidence|null
     */
    public function confidence(float $days, Carbon $routedAt, Carbon $dueAt): ?array
    {
        $q = $this->model() ? $this->model()['residual_quantiles'] : [];

        if (count($q) !== 101) {
            return null;
        }

        $daysToDue = $routedAt->diffInMinutes($dueAt) / 1440;
        $lateProbability = round(1 - $this->errorBelow($q, $daysToDue - $days), 3);
        $atRisk = self::readyAt($routedAt, $days)->greaterThan($dueAt);

        return [
            'low' => round(max(0.0, $days + $q[10]), 1),
            'high' => round(max(0.0, $days + $q[90]), 1),
            'lateProbability' => $lateProbability,
            'confidence' => round($atRisk ? $lateProbability : 1 - $lateProbability, 3),
            'atRisk' => $atRisk,
        ];
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
     * Share of past errors at or below $x, read from the 0th to 100th error percentiles.
     *
     * @param  list<float>  $q
     */
    private function errorBelow(array $q, float $x): float
    {
        if ($x < $q[0]) {
            return 0.0;
        }
        if ($x >= $q[100]) {
            return 1.0;
        }

        for ($i = 0; $i < 100; $i++) {
            if ($x < $q[$i + 1]) {
                $span = $q[$i + 1] - $q[$i];

                return ($i + ($span > 0 ? ($x - $q[$i]) / $span : 0)) / 100;
            }
        }

        return 1.0;
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
     * @return TurnaroundModel|false
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
            'residual_quantiles' => $this->quantiles($json['residual_quantiles'] ?? null),
            'metrics' => array_map('floatval', (array) ($json['metrics'] ?? [])),
            'baseline_metrics' => array_map('floatval', (array) ($json['baseline_metrics'] ?? [])),
            'algorithm' => (string) ($json['algorithm'] ?? 'Linear regression'),
            'trained_on' => (string) ($json['trained_on'] ?? 'unknown'),
        ];
    }

    /**
     * 101 ascending numbers, or an empty list when the file has none (confidence is then skipped).
     *
     * @return list<float>
     */
    private function quantiles(mixed $values): array
    {
        if (! is_array($values) || count($values) !== 101 || ! collect($values)->every(fn ($v) => is_numeric($v))) {
            return [];
        }

        $q = array_values(array_map('floatval', $values));
        $sorted = $q;
        sort($sorted);

        return $q === $sorted ? $q : [];
    }
}
