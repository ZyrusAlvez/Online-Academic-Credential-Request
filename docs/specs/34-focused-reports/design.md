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
- `causeOf(CredentialRequest)`: one of `DelayCategory::inferred()` (REQ-34-10), from the open
  visit, otherwise the visit with the most working days: a correction visit is Document
  correction; a college, CHAN or PRES is Pending signature; VPF is the documentary stamp; the
  Registrar, or no visit, is Registrar / evaluation. Delay notes no longer set it.
- `causeCounts(ReportFilter)`: one `{label, scope, count}` per inferred cause, zeros included,
  largest first. `scope` is `DelayCategory::description()`, the tooltip of the dashboard's
  information icon (REQ-34-03). The enum keeps its other cases for the notes recorded before.

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
Keeps `received`, `eligible`, `onTime` and `overdue`. The secondary indicators and the overall
`avgProcessingDays` are dropped, because nothing shows them any more (REQ-34-04).

`PerformanceMetrics::byCredential(ReportFilter)` returns, per credential type, the requests
released in the period that have `processing_working_days`: `name`, `released` (count), `days`
(average, 1 decimal) and `standard` (`credential_types.processing_days`). Longest average first,
then by name (REQ-34-09).

## Filters per report (REQ-34-13)
`Report::filters()` lists which of `type`, `evaluator` and `office` a report uses. `Report::shows()`
gives its Show options (`value => label`), or `[]` for none. `Admin\Reports\Index` renders only
those selects, and `filter()` builds the `ReportFilter` from those values alone, so a leftover URL
parameter never narrows a report that does not show it. `ReportFilter::$show` carries the chosen
option, and changing the report clears it.

`ReportKind::listed()` holds the four reports of the dropdown. `ReportKind::OfficeDocuments`
(`office-documents`) is reachable by URL only. While it is open, the dropdown also shows its option.

## Office Documents (`OfficeDocumentsReport`, REQ-34-12)
- Finished rows: `$filter->visits()` completed in the period with status Completed or Returned,
  the query behind Office Performance's Done, Returned and average. Show `finished`, `done` and
  `returned` narrow it.
- Pending rows: `$filter->heldVisits()`, the office is `OfficeVisit::holder()`, and the days are
  `workingDaysBetween(received_at ?? forwarded_at, now)`. Show `pending`.

## Drill-down links (Dashboard)
The links are `route('admin.reports', [period…, report, …])`:

| Dashboard number | Opens |
|---|---|
| Total requests | `transactions` |
| Ready on time | `transactions&show=on_time` (eligible, ready by the due date) |
| Overdue | `delay` (`DelayReport::overdue`) |
| Office name / avg / Done / Pending | `office-documents&office=ID` with no show / `finished` / `done` / `pending` |
| Credential name / Ready / avg | `transactions&type=ID&show=ready` (`ready_at` in the period, with a processing time) |
| Cause name / count | `delay&show=<cause value>` |

## Dashboard
`Admin\Dashboard` passes `metrics`, `offices`, `credentials` and `causes`. The view has the header
and the period inputs, then the three tiles, the office table (no highlight), the credential
table (an average above its standard is red and marked `data-over-standard`) and the causes.
