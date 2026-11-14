# 18 — The Admin Account · Tasks

- [x] `Role::Admin`, home pages; role `match`es cover Admin (REQ-18-01..03)
- [x] Routes: `admin.*`, `records*`, `/registrar` redirect; Admin pages, Dashboard and Reports moved to `App\Livewire\Admin` (REQ-18-04..06)
- [x] Policies: Admin pages to Admin; Records and uploads to Registrar and Admin (REQ-18-04..07)
- [x] Sidebar per role; Admin Dashboard without the Office of the Registrar queue, links to Records (new filters "Needs the Registrar", "Waiting for the requester"); Logs link to the record (REQ-18-02, 03, 08)
- [x] Accounts: Admin as a role to create and change to (REQ-18-09, 10)
- [x] `INITIAL_ADMIN_*`, `AdminSeeder`, demo admin (REQ-18-11, 12)
- [x] Docs: overview roles, superseded notes in specs 01, 02, 03, 07, 10, 12, 13, 14, 16; credentials; deployment
- [x] Pest tests for every REQ-18 criterion; moved pages' tests act as the Admin

## 2026-11-15 — the Admin is the only seeded account

- [x] Remove `RegistrarSeeder`, `config('uphsl.initial_registrar')` and `INITIAL_REGISTRAR_*` (`.env.example`, `phpunit.xml`) (REQ-18-11)
- [x] `AdminSeeder` refuses to seed without its settings (REQ-18-11)
- [x] `DemoSeeder` adds `registrar@example.com` and works the demo requests with it (REQ-18-12)
- [x] `app:reset-demo` checks `INITIAL_ADMIN_*` instead (REQ-18-13)
- [x] Docs: overview, spec 01 design, credentials, deployment
- [x] Tests: REQ-18-11..13; remove the REQ-01-15 tests
