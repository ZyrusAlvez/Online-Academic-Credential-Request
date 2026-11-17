<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\ActivityCategory;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CredentialTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An entry of the credential master list (spec 03).
 *
 * @property bool $requires_payment
 * @property int $processing_days
 * @property ?string $fee
 * @property bool $fee_per_term the fee is charged once per ticked term (REQ-29-05)
 * @property ?list<string> $free_for academic statuses that get it free (REQ-29-07)
 * @property ?list<string> $requirements
 * @property bool $requires_receiving_institution
 * @property bool $asks_term_and_year (REQ-29-04)
 * @property bool $is_active
 */
class CredentialType extends Model
{
    /** @use HasFactory<CredentialTypeFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'requires_payment',
        'fee',
        'fee_per_term',
        'free_for',
        'processing_days',
        'requirements',
        'special_conditions',
        'requires_receiving_institution',
        'asks_term_and_year',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_payment' => false,
        'fee_per_term' => false,
        'processing_days' => 5,
        'requires_receiving_institution' => false,
        'asks_term_and_year' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_payment' => 'boolean',
            'fee' => 'decimal:2',
            'fee_per_term' => 'boolean',
            'free_for' => 'array',
            'processing_days' => 'integer',
            'requirements' => 'array',
            'requires_receiving_institution' => 'boolean',
            'asks_term_and_year' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isFreeFor(?AcademicStatus $status): bool
    {
        return $status !== null && in_array($status->value, $this->free_for ?? [], true);
    }

    /**
     * What this credential costs the requester, or null when nothing is to be paid: the one fee rule
     * of the form, the summary and the workflow (spec 29). A fee per term is charged once per ticked
     * term (REQ-29-05); a status it is free for pays nothing (REQ-29-07).
     */
    public function feeFor(?AcademicStatus $status, int $terms = 1): ?string
    {
        if (! $this->requires_payment || $this->fee === null || $this->isFreeFor($status)) {
            return null;
        }

        $fee = (float) $this->fee * ($this->fee_per_term ? max(1, $terms) : 1);

        return number_format($fee, 2, '.', '');
    }

    /**
     * The statuses it is free for, as the requester reads them, e.g. "graduates" (REQ-29-08).
     */
    public function freeForLabel(): string
    {
        return collect(AcademicStatus::cases())
            ->filter(fn (AcademicStatus $status): bool => $this->isFreeFor($status))
            ->map(fn (AcademicStatus $status): string => $status->groupLabel())
            ->implode(' and ');
    }

    /**
     * The academic years a request can name, newest first: from the school year now under way (it
     * starts in June) back to 1950-1951 (REQ-29-04).
     *
     * @return list<string>
     */
    public static function academicYears(): array
    {
        $start = now()->month >= 6 ? now()->year : now()->year - 1;

        return array_map(fn (int $year): string => $year.'-'.($year + 1), range($start, 1950));
    }

    /**
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    /**
     * @param  Builder<CredentialType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active credential types that have a workflow, i.e. can be requested.
     *
     * @param  Builder<CredentialType>  $query
     */
    public function scopeRequestable(Builder $query): void
    {
        $query->where('is_active', true)->has('steps');
    }

    /**
     * The route as office codes, e.g. "REG → Dean → VPF → CHAN". Expects steps.department loaded.
     */
    public function routeSummary(): string
    {
        return $this->steps
            ->map(fn (WorkflowStep $step): string => $step->is_student_college ? 'Dean' : (string) $step->department?->code)
            ->implode(' → ');
    }

    /**
     * The automatic split of a processing period among a route's steps (REQ-30-02): the period
     * divided by the steps, rounded down, and the days left over one at a time to the steps in
     * route order. 7 days over 4 steps → [2, 2, 2, 1].
     *
     * @return list<int>
     */
    public static function splitDays(int $days, int $steps): array
    {
        if ($steps < 1) {
            return [];
        }

        $base = intdiv(max(0, $days), $steps);
        $left = max(0, $days) - $base * $steps;

        return array_map(fn (int $index): int => $base + ($index < $left ? 1 : 0), range(0, $steps - 1));
    }

    /**
     * Write the automatic split to this type's route (REQ-30-02). A query update: the change that
     * caused it (a step added or removed, the period edited) is already logged.
     */
    public function resplitDays(): void
    {
        $ids = $this->steps()->pluck('id');

        foreach (self::splitDays($this->processing_days, $ids->count()) as $index => $days) {
            WorkflowStep::whereKey($ids[$index])->update(['allotted_days' => $days]);
        }
    }

    /**
     * Renumber steps 1..n in their current order.
     */
    public function resequenceSteps(): void
    {
        $this->steps()->get()->each(function (WorkflowStep $step, int $index): void {
            $step->update(['position' => $index + 1]);
        });
    }

    public function activityCategory(): ActivityCategory
    {
        return ActivityCategory::Setup;
    }

    public function activityNoun(): string
    {
        return 'credential type';
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
