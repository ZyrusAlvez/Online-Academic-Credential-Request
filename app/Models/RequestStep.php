<?php

namespace App\Models;

use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One office's signature on a request: a snapshot of a workflow step at routing time.
 *
 * @property int $position
 * @property StepStatus $status
 * @property ?Carbon $acted_at
 * @property Carbon $updated_at
 */
class RequestStep extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'position',
        'department_id',
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
            'status' => StepStatus::class,
            'acted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
