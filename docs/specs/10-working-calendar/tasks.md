# 10 — Working Calendar & Due Dates · Tasks

- [x] Migration, `NonWorkingDay` model + factory, `NonWorkingDayKind` enum, `uphsl.work_week`
- [x] `WorkingCalendar` service with cache invalidation (REQ-10-01..03)
- [x] `RequestWorkflow::approve()` uses `addWorkingDays` (REQ-10-04, 05); drop REQ-07-01
- [x] Calendar page, form, policy, route, nav (REQ-10-06, 08)
- [x] `NonWorkingDaySeeder` + data migration (REQ-10-07)
- [x] Pest tests for every REQ-10 criterion; data dictionary updated
- [x] `composer qa` green (CI, deploy run on staging)
