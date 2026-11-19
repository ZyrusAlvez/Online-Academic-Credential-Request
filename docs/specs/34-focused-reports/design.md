# 34 — Focused Reports and Dashboard · Design

## Records and Reports
**Records** is the live list: where each request is now and what it holds. **Reports** show the
Registrar's performance over a period, overall (offices, months) and per evaluator. A dashboard
number about now (an office's pending papers) opens Records; a period result opens a report or is
a plain number (user's decision, 2026-11-20).

## Reports (`App\Enums\ReportKind`)
`Office`, `Evaluator`, `Transactions`, `Delay`, `Monthly`, all in the dropdown.
`Admin\Reports\Index` defaults to `office`, and `ReportKind::tryFrom()` falls back to `Office`, so
old links such as `?report=daily` still open a report (REQ-34-05).

The removed classes are `PeriodicReport`, `SignatoryReport`, `TrackingReport`, `ComplianceReport`,
`ReleaseReport` and `OfficeDocumentsReport`. A request's full routing history stays on its record
page (Time in Each Office, REQ-17-14). The customer's waiting states stay as Records filters.

## Office Performance (`DepartmentReport`)
`DepartmentReport::offices(ReportFilter)` returns one array per office:
`id, name, days, done, returned, pending, oldest, onTime`.

- `days`, `done`, `returned` and `onTime` come from the visits completed or returned in the
  period. `onTime` keeps REQ-30-09.
- `pending` and `oldest` come from `ReportFilter::heldVisits()`, grouped by
  `OfficeVisit::holderSql()` (in transit = at the sender). `oldest` is the
  `MIN(COALESCE(received_at, forwarded_at))` turned into working days up to now by
  `WorkingCalendar::workingDaysBetween()`.
- Sorting: `days` descending, offices without a finished document last; ties are broken by
  `oldest`, then by name.

The Dashboard uses `offices()` directly, because it needs `id` for the Records link (REQ-34-02).

## Evaluator Performance (`EvaluatorReport`, REQ-34-15)
The report removed in REQ-34-05 and restored: per `evaluated_by`, the requests whose
`evaluation_started_at` is in the period (taken up), with `requirements_completed_at` (complete),
with a `CustomerActionRequested` event (returned for clarification), with a deficiency (sent for
compliance), declined, and `AVG(submitted_at → evaluation_started_at)` in hours; plus the open
workload now (not accepted, under evaluation or waiting for the requester).

## Delay causes (`DelayReport`)
- `overdue(ReportFilter)`: the eligible requests not ready for release by their due date. The
  report rows and the counts share it.
- `causeOf(CredentialRequest)`: one of `DelayCategory::inferred()` (REQ-34-10), from the open
  visit, otherwise the visit with the most working days.
- `causeCounts(ReportFilter)`: one `{value, label, scope, count}` per inferred cause, zeros
  included, largest first. `scope` is the tooltip of the dashboard's information icon (REQ-34-03).
- Its own filter is the cause (`shows()`, "All causes" first).

## Transaction Report (`TransactionReport`, REQ-34-07)
Requests whose `submitted_at` falls in the period, ordered by it, filtered by the evaluator only.
The query eager-loads `credentialType`, `evaluator`, `openVisit.department`, `visits.department`
and `delayNotes`. The requester is the request's own `requester_name` (REQ-15-08).

## Monthly Summary (`MonthlySummaryReport`, REQ-34-08)
`Admin\Reports\Index` turns the chosen `year` into `{year}-01-01 … {year}-12-31`, and the report
lists every month of that range.

- Received: counted by the month of `submitted_at`.
- Due, on time, late and pending: `PerformanceMetrics::eligible()` (due not after now), grouped by
  the month of `effective_due_at`, so the three parts always add up to Due. A month after today
  has no due requests.
- Average processing: by the month of `ready_at` (REQ-34-11).

## PerformanceMetrics
Keeps `received`, `eligible`, `onTime` and `overdue`. `byCredential(ReportFilter)` returns, per
credential type, the requests made ready in the period that have `processing_working_days`: `id`,
`name`, `ready` (count), `days` (average, 1 decimal) and `standard`
(`credential_types.processing_days`). Longest average first, then by name (REQ-34-09).

## Filters per report (REQ-34-13)
`Report::filters()` lists which of `type`, `evaluator` and `office` a report uses. `Report::shows()`
gives its own filter's options (`value => label`, the `''` option first), or `[]` for none;
`Report::showLabel()` names that filter. `Admin\Reports\Index` renders only those selects, and
`filter()` builds the `ReportFilter` from those values alone, so a leftover URL parameter never
narrows a report that does not show it. The Monthly Summary shows a Year select in place of
From and To.

## Sorting, pages and CSV (REQ-34-14, 16)
`Admin\Reports\Index` keeps `sort` (the column index) and `direction` in the URL. It collects the
report's rows, sorts them in PHP (numbers by value, other cells naturally and case-insensitively,
empty cells last in both directions), and pages them 50 at a time with a `LengthAwarePaginator`
on Livewire's page (`WithPagination`), shown by `flux:table :paginate`. Changing the report clears
`show` and `sort`; changing any filter or the sort resets the page. Headers other than the first
carry `[&>div]:justify-center`, so they are centered like their values.

`export()` streams the same sorted rows, all of them, with the column headers, as CSV
(`fputcsv`), named `{report}-{from}-{to}.csv` or `monthly-{year}.csv`.

## Dashboard links

| Dashboard number | Opens |
|---|---|
| Total requests | `admin.reports?report=transactions` with the period |
| Overdue | `admin.reports?report=delay` with the period |
| Cause name / count | `admin.reports?report=delay&show=<cause value>` |
| Office Pending | `records?status=processing&office=ID` |

Ready on time, the office name, average and Done, and the credential table are plain numbers.

## Dashboard
`Admin\Dashboard` passes `metrics`, `offices`, `credentials`, `causes` and `period`. The view has
the header and the period inputs, then the three tiles, the office table, the credential table
(an average above its standard is red and marked `data-over-standard`) and the causes.
