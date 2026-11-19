# 34 — Focused Reports and Dashboard · Design

## Reports (`App\Enums\ReportKind`)
`Office`, `Delay`, `Transactions`, `Monthly`. `Admin\Reports\Index` defaults to `office`, and
`ReportKind::tryFrom()` falls back to `Office`, so old links such as `?report=daily` still open a
report (REQ-34-05).

The removed classes are `PeriodicReport`, `EvaluatorReport`, `SignatoryReport`, `TrackingReport`,
`ComplianceReport` and `ReleaseReport`. A request's full routing history stays on its record page
(Time in Each Office, REQ-17-14). The customer's waiting states stay as Records filters.

## Office Performance (`DepartmentReport`)
`DepartmentReport::offices(ReportFilter)` returns one array per office:
`id, name, days, done, returned, pending, oldest, onTime`.

- `days`, `done`, `returned` and `onTime` come from the visits completed or returned in the
  period. These are the aggregates from before, and `onTime` keeps REQ-30-09.
- `pending` and `oldest` come from `ReportFilter::heldVisits()`, grouped by `OfficeVisit::holderSql()`
  (in transit = at the sender). `oldest` is the
  `MIN(COALESCE(received_at, forwarded_at))` turned into working days up to now by
  `WorkingCalendar::workingDaysBetween()`.
- Sorting: `days` descending, offices without a finished document last; ties are broken by `oldest`, then by name.

`rows()` maps these to the report's columns. The Dashboard uses `offices()` directly, because it
needs `id` for the Records link (REQ-34-02).

## Delay causes (`DelayReport`)
- `overdue(ReportFilter)`: the eligible requests that were not released by their due date. The
  report rows and the counts share it.
- `causeOf(CredentialRequest)`: the latest delay note's category, otherwise the cause inferred
  from the stage, as before.
- `causeCounts(ReportFilter)`: `label => count`, largest first. The Dashboard uses it (REQ-34-03).

## Transaction Record (`TransactionReport`)
Requests whose `submitted_at` falls in the period, ordered by it. The query eager-loads
`credentialType`, `evaluator`, `openVisit.department`, `visits.department` and `delayNotes`. The
requester is the request's own `requester_name`, and the program is its `program` (REQ-15-08).

## Monthly Summary (`MonthlySummaryReport`)
The months run from the period's start to `min(end, now)`.

- Received: counted by the month of `submitted_at`.
- Due, on time, late and pending: `PerformanceMetrics::eligible()`, grouped by the month of
  `effective_due_at`, so the three parts always add up to Due.
- Average processing: by the month of `released_at`, as in REQ-07-11.

## PerformanceMetrics
Keeps `received`, `eligible`, `onTime`, `overdue` and `avgProcessingDays`. The secondary
indicators are dropped, because nothing shows them any more (REQ-34-04).

## Dashboard
`Admin\Dashboard` passes `metrics`, `offices` and `causes`. The view has the header, the period
inputs and the Reports button, then the four tiles, the office table and the causes.
