<?php

namespace App\Models;

use App\Enums\DelayCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The recorded reason for a delay (spec 05). Append-only, like the ledger.
 *
 * @property DelayCategory $category
 * @property string $reason
 * @property ?string $corrective_action
 * @property Carbon $created_at
 */
class DelayNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'office_visit_id',
        'department_id',
        'category',
        'reason',
        'corrective_action',
        'recorded_by',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Delay notes are append-only.'));
        static::deleting(fn () => throw new LogicException('Delay notes are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DelayCategory::class,
            'created_at' => 'datetime',
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
