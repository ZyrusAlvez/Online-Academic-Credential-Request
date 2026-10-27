<?php

namespace App\Models;

use App\Enums\RouteTask;
use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One step of a request's route, copied from its credential type at acceptance (spec 05).
 *
 * @property int $position
 * @property int $department_id
 * @property RouteTask $task
 * @property StepStatus $status
 * @property ?Carbon $completed_at
 */
class RequestStep extends Model
{
    protected $fillable = [
        'position',
        'department_id',
        'task',
        'instructions',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'task' => RouteTask::class,
            'status' => StepStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CredentialRequest, $this>
     */
    public function credentialRequest(): BelongsTo
    {
        return $this->belongsTo(CredentialRequest::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<OfficeVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(OfficeVisit::class)->orderBy('id');
    }
}
