<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One entry of the system-wide audit log (spec 14). Append-only: rows can be created but never
 * updated or deleted (REQ-14-07). Written only by App\Actions\Audit\RecordActivity.
 *
 * @property ?int $actor_id
 * @property ?string $actor_name
 * @property ?Role $actor_role
 * @property ActivityCategory $category
 * @property string $action
 * @property string $description
 * @property ?string $reference_no
 * @property ?int $department_id
 * @property ?array<string, array{0: ?string, 1: ?string}> $changes
 * @property ?string $ip_address
 * @property Carbon $created_at
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'category',
        'action',
        'description',
        'reference_no',
        'department_id',
        'subject_type',
        'subject_id',
        'changes',
        'ip_address',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new LogicException('Activity logs are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_role' => Role::class,
            'category' => ActivityCategory::class,
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
