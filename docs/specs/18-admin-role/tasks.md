# 18 — The Admin Account · Tasks

- [x] `Role::Admin`, home pages; role `match`es cover Admin (REQ-18-01..03)
- [x] Routes: `admin.*`, `records*`, `/registrar` redirect; Admin pages, Dashboard and Reports moved to `App\Livewire\Admin` (REQ-18-04..06)
- [x] Policies: Admin pages to Admin; Records and uploads to Registrar and Admin (REQ-18-04..07)
- [x] Sidebar per role; Admin Dashboard without the Office of the Registrar queue, links to Records (new filters "Needs the Registrar", "Waiting for the requester"); Logs link to the record (REQ-18-02, 03, 08)
- [x] Accounts: Admin as a role to create and change to (REQ-18-09, 10)
- [x] `INITIAL_ADMIN_*`, `AdminSeeder`, demo admin (REQ-18-11, 12)
- [x] Docs: overview roles, superseded notes in specs 01, 02, 03, 07, 10, 12, 13, 14, 16; credentials; deployment
- [x] Pest tests for every REQ-18 criterion; moved pages' tests act as the Admin
