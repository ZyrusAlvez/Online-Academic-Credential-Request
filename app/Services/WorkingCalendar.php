<?php

namespace App\Services;

use App\Models\NonWorkingDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Working days for due dates and time per office (spec 10): the configured work week minus the
 * working calendar's holidays and closures. Non-working dates are loaded once per process and
 * cached; NonWorkingDay model events call forget().
 */
class WorkingCalendar
{
    private const CACHE_KEY = 'working-calendar';

    /** @var array<string, true>|null Y-m-d => true */
    private ?array $closed = null;

    public function isWorkingDay(CarbonInterface $day): bool
    {
        return in_array($day->dayOfWeekIso, $this->workWeek(), true)
            && ! isset($this->closedDates()[$day->format('Y-m-d')]);
    }

    /**
     * End of the last of $days working days after $from's date (REQ-10-02).
     */
    public function addWorkingDays(CarbonInterface $from, int $days): Carbon
    {
        $day = CarbonImmutable::instance($from)->startOfDay();

        while ($days > 0) {
            $day = $day->addDay();

            if ($this->isWorkingDay($day)) {
                $days--;
            }
        }

        return Carbon::instance($day->endOfDay());
    }

    /**
     * Working days after $start's date up to and including $end's date (REQ-10-03).
     */
    public function workingDaysBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        $day = CarbonImmutable::instance($start)->startOfDay();
        $last = CarbonImmutable::instance($end)->startOfDay();
        $count = 0;

        while ($day->lessThan($last)) {
            $day = $day->addDay();

            if ($this->isWorkingDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    public function forget(): void
    {
        $this->closed = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<int>
     */
    private function workWeek(): array
    {
        return array_map(intval(...), (array) config('uphsl.work_week', [1, 2, 3, 4, 5, 6]));
    }

    /**
     * @return array<string, true>
     */
    private function closedDates(): array
    {
        return $this->closed ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            $dates = [];

            foreach (NonWorkingDay::query()->pluck('date') as $date) {
                $dates[$date->format('Y-m-d')] = true;
            }

            return $dates;
        });
    }
}
