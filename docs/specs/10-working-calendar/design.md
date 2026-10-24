# 10 — Working Calendar & Due Dates · Design

## Data

`non_working_days`: `id`, `date` (date, unique), `name` (150), `kind` (20,
`App\Enums\NonWorkingDayKind`: `holiday`, `special`, `closure`), `created_by` (nullable FK →
users, null on delete), timestamps.

Work week: `config('uphsl.work_week')`, ISO weekday numbers, default `[1, 2, 3, 4, 5, 6]`
(Monday–Saturday). The client document's own example counts Monday–Friday; switching is a
one-line change.

## `App\Services\WorkingCalendar` (singleton)

```php
isWorkingDay(CarbonInterface $day): bool
addWorkingDays(CarbonInterface $from, int $days): Carbon   // end of day of the nth working day after $from's date
workingDaysBetween(CarbonInterface $start, CarbonInterface $end): int // working days in (start date, end date]
```

- Non-working dates are loaded once per process from the cache (`working-calendar`), which
  `NonWorkingDay` model events clear on save and delete.
- Due dates are stored, never recomputed, so a calendar edit affects only due dates set later
  (REQ-10-05). Approved pauses (spec 04/05) produce a separate adjusted due date.
- Today the clock starts when the registrar routes a request (`RequestWorkflow::approve()`);
  spec 04 moves it to acceptance. Spec 10 replaces REQ-07-01 (weekdays only).

## Screens

`Registrar\Calendar\Index` at `/registrar/calendar` ("Working Calendar" under Administration):
year picker, the year's non-working days with kind badges, add/edit modal, delete with
confirmation, and a note naming the work week. `NonWorkingDayPolicy`: registrar only.

## Reference data

`NonWorkingDaySeeder` (`updateOrCreate` by date): New Year's Day, Araw ng Kagitingan, Labor Day,
Independence Day, National Heroes Day (last Monday of August), Bonifacio Day, Christmas Day,
Rizal Day (regular); Chinese New Year, Ninoy Aquino Day, All Saints' Day, Feast of the Immaculate
Conception, Last Day of the Year, Black Saturday (special); Maundy Thursday and Good Friday
(regular). Eid holidays and one-off proclamations are added by the registrar.

Deploys only run `migrate`, so a data migration runs the seeder on staging and production. It is
skipped under the test runner, where tests build their own calendar.
