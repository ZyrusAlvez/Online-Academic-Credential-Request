<?php

namespace App\Models;

use App\Enums\ActivityCategory;
use App\Models\Concerns\LogsActivity;
use Database\Factories\PurposeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A request purpose offered on New Request (spec 13). Never deleted, only deactivated; requests
 * keep a copy of the name.
 *
 * @property string $name
 * @property bool $is_active
 */
class Purpose extends Model
{
    /** @use HasFactory<PurposeFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
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
     * @param  Builder<Purpose>  $query
     * @return Builder<Purpose>
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
        return 'purpose';
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
