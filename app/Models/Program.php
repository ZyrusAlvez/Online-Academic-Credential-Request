<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A program or strand of a college, offered to requesters on New Request and Settings → Profile (spec 12). Never deleted,
 * only deactivated.
 *
 * @property string $name
 * @property bool $is_active
 */
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'department_id',
        'name',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activityCategory(): ActivityCategory
    {
        return ActivityCategory::Setup;
    }

    public function activityNoun(): string
    {
        return 'program';
    }

    public function activityLabel(): string
    {
        return $this->name;
    }

    protected function activityDepartmentId(): ?int
    {
        return $this->department_id;
    }
}
