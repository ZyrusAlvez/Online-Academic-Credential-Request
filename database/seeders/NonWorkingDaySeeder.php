<?php

namespace Database\Seeders;

use App\Enums\NonWorkingDayKind;
use App\Models\NonWorkingDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Philippine national holidays for 2026–2027 (spec 10, REQ-10-07). Eid holidays and one-off
 * proclamations are added by the registrar on the Working Calendar page. Safe to run again.
 */
class NonWorkingDaySeeder extends Seeder
{
    /** month-day => [name, kind] */
    private const FIXED = [
        '01-01' => ["New Year's Day", NonWorkingDayKind::Holiday],
        '04-09' => ['Araw ng Kagitingan', NonWorkingDayKind::Holiday],
        '05-01' => ['Labor Day', NonWorkingDayKind::Holiday],
        '06-12' => ['Independence Day', NonWorkingDayKind::Holiday],
        '08-21' => ['Ninoy Aquino Day', NonWorkingDayKind::Special],
        '11-01' => ["All Saints' Day", NonWorkingDayKind::Special],
        '11-30' => ['Bonifacio Day', NonWorkingDayKind::Holiday],
        '12-08' => ['Feast of the Immaculate Conception of Mary', NonWorkingDayKind::Special],
        '12-25' => ['Christmas Day', NonWorkingDayKind::Holiday],
        '12-30' => ['Rizal Day', NonWorkingDayKind::Holiday],
        '12-31' => ['Last Day of the Year', NonWorkingDayKind::Special],
    ];

    /** Dates that move every year: date => [name, kind] */
    private const MOVABLE = [
        '2026-02-17' => ['Chinese New Year', NonWorkingDayKind::Special],
        '2026-04-02' => ['Maundy Thursday', NonWorkingDayKind::Holiday],
        '2026-04-03' => ['Good Friday', NonWorkingDayKind::Holiday],
        '2026-04-04' => ['Black Saturday', NonWorkingDayKind::Special],
        '2027-02-06' => ['Chinese New Year', NonWorkingDayKind::Special],
        '2027-03-25' => ['Maundy Thursday', NonWorkingDayKind::Holiday],
        '2027-03-26' => ['Good Friday', NonWorkingDayKind::Holiday],
        '2027-03-27' => ['Black Saturday', NonWorkingDayKind::Special],
    ];

    private const YEARS = [2026, 2027];

    public function run(): void
    {
        foreach ($this->days() as $date => [$name, $kind]) {
            NonWorkingDay::updateOrCreate(['date' => $date], ['name' => $name, 'kind' => $kind]);
        }
    }

    /**
     * @return array<string, array{string, NonWorkingDayKind}>
     */
    private function days(): array
    {
        $days = self::MOVABLE;

        foreach (self::YEARS as $year) {
            foreach (self::FIXED as $monthDay => $day) {
                $days["{$year}-{$monthDay}"] = $day;
            }

            // National Heroes Day: last Monday of August.
            $heroes = Carbon::create($year, 8, 31)->startOfDay();
            $days[$heroes->subDays(($heroes->dayOfWeekIso + 6) % 7)->format('Y-m-d')] = ['National Heroes Day', NonWorkingDayKind::Holiday];
        }

        ksort($days);

        return $days;
    }
}
