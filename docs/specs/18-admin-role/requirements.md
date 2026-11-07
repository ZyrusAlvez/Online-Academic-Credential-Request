# 18 — The Admin Account · Requirements

Source: *Proposed Workflow*:

- **Module 12**, "Administration and Audit Trail": user roles, fees, routing configuration,
  working calendar, system logs and access control.
- **Section X**: "Configurable routing" (*authorized administrators* update signatories, routing
  sequences, fees and processing periods) and "Role-based access" (customer records, IDs,
  academic documents, internal remarks and reports are restricted by assigned role).
- **Section IX**: the performance dashboard is for "the Registrar and *authorized
  administrators*".

The BRD does not name the administrators' account. This spec reads "authorized administrators"
as a fourth account type, **Admin**.

Decided by the user on 2026-11-07:

- There are four account types: Student (shown as Requester), Registrar, Department Staff and
  Admin.
- The Admin runs the system: the Administration pages, the performance Dashboard and Reports.
- The Registrar works the requests from Initial Evaluation to Release.
- Both can look up any request in Records and open its uploaded files.

This replaces:

- REQ-01-10's home pages.
- REQ-16-01's sidebar items.
- Every criterion in specs 02, 03, 07, 10, 12, 13 and 14 that gives "the registrar" an
  Administration page, the Dashboard or Reports. Those now belong to the Admin; each spec
  carries a note.

## User stories

- **US-18-1** As the Admin, I want to manage credential types, offices, programs, purposes,
  accounts, the working calendar and the logs, so that the system is set up and audited by one
  responsible role.
- **US-18-2** As the Admin, I want the performance dashboard and the reports, so that I can see
  how the offices perform.
- **US-18-3** As the Registrar, I want only the pages I work in, Initial Evaluation to Release,
  and Records to look a request up.
- **US-18-4** As the university, I want the first Admin to come from the deployment's settings,
  like the first registrar, so that a new install can be administered.

## Acceptance criteria

### Roles and pages
- **REQ-18-01** THE SYSTEM SHALL have four roles: `student` (shown as *Requester*), `registrar`,
  `department` (shown as *Department Staff*) and `admin` (shown as *Admin*).
- **REQ-18-02** THE SYSTEM SHALL give the Admin the sidebar groups **Overview** (Dashboard,
  Records, Reports) and **Administration** (Credential Types, Departments, Programs, Purposes,
  Accounts, Working Calendar, Logs). Opening `/dashboard` or logging in as the Admin SHALL lead
  to the Dashboard (`/admin`).
- **REQ-18-03** THE SYSTEM SHALL give the Registrar the sidebar items Initial Evaluation,
  Payment Validation, In Progress, Final Quality Check, Release and Records. Opening
  `/dashboard` or logging in as the Registrar SHALL lead to Initial Evaluation. `/registrar`
  SHALL redirect there.
- **REQ-18-04** THE Dashboard, Reports and the Administration pages SHALL be at `/admin/…`. IF
  anyone but the Admin opens one, or calls one of its actions, THEN THE SYSTEM SHALL respond 403.
- **REQ-18-05** THE stage queues and the registrar's working page (`/registrar/requests/{id}`)
  SHALL stay the Registrar's. IF the Admin opens one, or calls one of its actions, THEN THE
  SYSTEM SHALL respond 403.
- **REQ-18-06** THE SYSTEM SHALL serve Records at `/records` and a request's read-only record at
  `/records/{id}`, to the Registrar and the Admin only. Other roles SHALL get 403.
- **REQ-18-07** THE SYSTEM SHALL let the Registrar and the Admin open a request's uploaded valid
  ID, SPA and IDs, and proof of payment. Everyone else keeps the rules of REQ-01-20 and spec 11.

### Dashboard
- **REQ-18-08** THE Admin's Dashboard SHALL keep:
  - the status counts;
  - the performance indicators for a period and the link to Reports;
  - where requests are waiting, by office;
  - the most overdue requests.

  Its links SHALL open Records (filtered by status or office) or a request's record. It SHALL
  NOT show the Office of the Registrar queue: the Registrar works those papers from In Progress
  (REQ-17-12).

### Accounts
- **REQ-18-09** THE Admin SHALL create staff accounts as Department Staff, Registrar or Admin. THE
  Admin SHALL change any other account's role to Requester, Department Staff, Registrar or
  Admin, with the rules of REQ-02-15/16. Only Department Staff SHALL need a department.
- **REQ-18-10** THE SYSTEM SHALL NOT let anyone change their own role or deactivate their own
  account (REQ-02-15), so at least one Admin always remains.

### The first Admin
- **REQ-18-11** THE SYSTEM SHALL provide a seeder that creates the first Admin from
  `INITIAL_ADMIN_EMAIL` and `INITIAL_ADMIN_PASSWORD`:
  - It runs as part of the normal seeding.
  - WHEN an account with that email already exists THE SYSTEM SHALL leave it unchanged.
  - IF the settings are not set THEN THE SYSTEM SHALL skip it with a warning and go on seeding.
    Existing installs keep seeding until they add the settings.
  - `INITIAL_REGISTRAR_*` (REQ-01-15) is unchanged.
- **REQ-18-12** THE demo data (local and staging only, REQ-07-09) SHALL include an Admin account,
  `admin@example.com` with the demo password `@Test123` (REQ-07-09).
