<?php

namespace App\Models;

use App\Enums\NonWorkingDayKind;
use App\Services\WorkingCalendar;
use Database\Factories\NonWorkingDayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A holiday or closure in the working calendar (spec 10).
 *
 * @property Carbon $date
 * @property string $name
 * @property NonWorkingDayKind $kind
 */
class NonWorkingDay extends Model
{
    /** @use HasFactory<NonWorkingDayFactory> */
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => NonWorkingDayKind::class,
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(WorkingCalendar::class)->forget());
        static::deleted(fn () => app(WorkingCalendar::class)->forget());
    }
}
