# 03 — Credential Master List & Routes · Tasks

- [x] Migration: `requirements`, `special_conditions`, `requires_receiving_institution` on
      credential types; `task` on workflow steps; `RouteTask` enum
- [x] Seeders: offices (REQ-02-01, 13) and the 17-type master list with `install()` (REQ-03-01, 12)
- [x] Data migration installing the master list on deploy
- [x] Credential types page: fee, period, route, new fields (REQ-03-02..05)
- [x] Route builder: task on add and edit, Dean label (REQ-03-06..09)
- [x] Demo seeder and seed-dependent tests updated to the new offices and types
- [x] Pest tests for every changed criterion; data dictionary updated
- [x] `composer qa` green (CI, deploy run on staging)
