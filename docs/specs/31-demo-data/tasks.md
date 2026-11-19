# 31 — Demo Data at Scale · Tasks

- [x] `app:reset-demo` always loads the requests; `--requests` removed (REQ-31-01)
- [x] `DrivesDemoWorkflow` trait shared by both request seeders; clock never passes now (REQ-31-05)
- [x] `DemoBulkSeeder`: requesters, two more registrars, coverage set, random fill (REQ-31-02..04)
- [x] Quiet and reproducible (REQ-31-05); `uphsl.demo.*` config
- [x] credentials.md: reset-demo, generated accounts, current college list
- [x] Pest tests for REQ-31-01..05; REQ-23-07/08 tests renamed
- [x] `app:reset-demo --as-of` / `DEMO_AS_OF`, read before the wipe; the demo clock starts from it (REQ-31-06)
- [x] Due dates 5, 3, 1 working days past, that day, and 1 and 2 ahead; that morning's applications (REQ-31-07)
- [x] Pest tests for REQ-31-06, 07; credentials.md
