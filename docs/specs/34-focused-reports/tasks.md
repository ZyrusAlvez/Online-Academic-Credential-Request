# 34 — Focused Reports and Dashboard · Tasks

- [x] `ReportKind` down to four; Office Performance default and fallback (REQ-34-05)
- [x] `DepartmentReport` → Office Performance with pending now and oldest pending (REQ-34-06)
- [x] `DelayReport::causeOf()` / `causeCounts()`; new `TransactionReport` (REQ-34-07)
- [x] `MonthlySummaryReport` with tying totals (REQ-34-08)
- [x] Remove Periodic, Evaluator, Signatory, Tracking, Compliance and Release reports
- [x] Dashboard: four tiles, office table, causes of delay; nothing else (REQ-34-01..04)
- [x] Supersede REQ-07-03, 04, 05, 06, 12..17, 19 and REQ-20-12; reword REQ-07-11, REQ-18-08
- [x] Pest tests for REQ-34-01..08; update the tests that used the removed reports
- [x] Dashboard: three tiles, no oldest-pending column (REQ-34-01, 02, 04)
- [x] `PerformanceMetrics::byCredential()` and the credential table (REQ-34-09)
- [x] Pest tests for REQ-34-09; update REQ-34-01, 02, 04
- [x] Four delay causes by office, notes no longer set the cause (REQ-34-10, REQ-07-18)
- [x] Information icon with the cause's scope on the dashboard (REQ-34-03)
- [x] Pest tests for REQ-34-10; update REQ-34-03
- [x] Clock stops at ready for release: metrics, delay, transaction and monthly reports; processing
  time stored when ready (REQ-34-11, REQ-07-11, REQ-11-08)
