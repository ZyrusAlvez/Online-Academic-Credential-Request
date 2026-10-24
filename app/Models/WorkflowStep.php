<?php

namespace App\Models;

use App\Enums\RouteTask;
use Database\Factories\WorkflowStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A step in a credential type's route template (spec 03).
 *
 * @property int $position
 * @property bool $is_student_college
 * @property RouteTask $task
 */
class WorkflowStep extends Model
{
    /** @use HasFactory<WorkflowStepFactory> */
    use HasFactory;

    public const STUDENT_COLLEGE_LABEL = "Dean (student's college)";

    protected $fillable = [
        'position',
        'department_id',
        'is_student_college',
        'task',
        'instructions',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'task' => 'sign',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_student_college' => 'boolean',
            'task' => RouteTask::class,
        ];
    }

    /**
     * @return BelongsTo<CredentialType, $this>
     */
    public function credentialType(): BelongsTo
    {
        return $this->belongsTo(CredentialType::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function label(): string
    {
        return $this->is_student_college
            ? self::STUDENT_COLLEGE_LABEL
            : (string) $this->department?->name;
    }
}
