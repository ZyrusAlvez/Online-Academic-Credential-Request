# 03 — Credential Master List & Routes · Tasks

- [ ] Migration: `requirements`, `special_conditions`, `requires_receiving_institution` on
      credential types; `task` on workflow steps; `RouteTask` enum
- [ ] Seeders: offices (REQ-02-01, 13) and the 17-type master list with `install()` (REQ-03-01, 12)
- [ ] Data migration installing the master list on deploy
- [ ] Credential types page: fee, period, route, new fields (REQ-03-02..05)
- [ ] Route builder: task on add and edit, Dean label (REQ-03-06..09)
- [ ] Demo seeder and seed-dependent tests updated to the new offices and types
- [ ] Pest tests for every changed criterion; data dictionary updated
- [ ] `composer qa` green (CI, deploy run on staging)
