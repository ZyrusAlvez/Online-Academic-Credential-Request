# 07 — Performance Dashboard & Reports · Design

## Filters

`App\Reports\ReportFilter` (final, `fromArray`/`toArray`, like `RegistrarRequestFilter`): `from`,
`to` (dates, Asia/Manila; default the current month), `type` (credential type), `evaluator`
(registrar user), `office` (department). Request-level reports apply `office` as "the request
has visited that office"; office-level reports apply it to the visits themselves.

## Indicators

`App\Reports\PerformanceMetrics::for(ReportFilter)` computes every indicator in single grouped
queries over stored values (`effective_due_at`, `released_at`, `processing_working_days`,
`office_visits.working_days`), so reports never re-run calendar math per row:

| Indicator | Definition |
|---|---|
| received | `submitted_at` in the period |
| eligible | accepted, not cancelled or declined, `effective_due_at` in [from, min(to, today)] |
| on time | eligible with `released_at ≤ effective_due_at` |
| overdue | eligible − on time |
| average processing time | AVG(`processing_working_days`) of requests released in the period |
| average office time | SUM(`working_days`) ÷ COUNT of visits completed or returned in the period |
| overdue workload | being processed and past `effective_due_at`, now |
| returns | visits returned in the period (and % of visits closed in the period) |
| initial evaluation time | AVG(`evaluation_started_at − submitted_at`) in hours, evaluations started in the period |

## Reports

`App\Reports\Report` (interface): `title()`, `description()`, `columns()`, `rows(ReportFilter)`.
`App\Enums\ReportKind` maps each slug to its class. Classes in `app/Reports/`:
`PeriodicReport` (daily, weekly, monthly), `EvaluatorReport`, `DepartmentReport`,
`SignatoryReport`, `TrackingReport`, `DelayReport`, `ComplianceReport`, `ReleaseReport`.

The delay report's cause is the latest `delay_notes.category`; without one it is inferred from the
stage: a correction visit → document correction; Dean (college) → academic department; VP Finance
→ documentary stamp; Chancellor or President → pending signature; Registrar → registrar
evaluation. The holding office is reported separately.

## Screens

- `Registrar\Dashboard`: work-queue tiles, the period's indicators (with a link to the reports),
  the bottleneck bar list, where requests wait, most overdue, the Registrar's queue.
- `Registrar\Reports\Index` (`/registrar/reports?report=…`): report picker, shared filter bar,
  generic table; "Export CSV" → `ReportsCsvController` (`/registrar/reports/{report}/export`).
- `App\Support\Csv::safeCell` is shared by both CSV exports.
