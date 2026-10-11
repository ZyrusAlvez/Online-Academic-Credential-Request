<?php

namespace App\Models;

use App\Enums\DepartmentType;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property DepartmentType $type
 * @property bool $is_active
 */
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /** Code of the Office of the Registrar; registrar accounts act on its steps. */
    public const REGISTRAR_CODE = 'REG';

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
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
            'type' => DepartmentType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @param  Builder<Department>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Department>  $query
     */
    public function scopeColleges(Builder $query): void
    {
        $query->where('type', DepartmentType::College);
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => $value,
            set: fn (string $value): string => strtoupper(trim($value)),
        );
    }
}
