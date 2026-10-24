<?php

namespace App\Models;

use Database\Factories\CredentialTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An entry of the credential master list (spec 03).
 *
 * @property bool $requires_payment
 * @property ?string $fee
 * @property ?list<string> $requirements
 * @property bool $requires_receiving_institution
 * @property bool $is_active
 */
class CredentialType extends Model
{
    /** @use HasFactory<CredentialTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'requires_payment',
        'fee',
        'processing_days',
        'requirements',
        'special_conditions',
        'requires_receiving_institution',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'requires_payment' => false,
        'processing_days' => 5,
        'requires_receiving_institution' => false,
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
            'processing_days' => 'integer',
            'requirements' => 'array',
            'requires_receiving_institution' => 'boolean',
            'is_active' => 'boolean',
        ];
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
     * Renumber steps 1..n in their current order.
     */
    public function resequenceSteps(): void
    {
        $this->steps()->get()->each(function (WorkflowStep $step, int $index): void {
            $step->update(['position' => $index + 1]);
        });
    }
}
