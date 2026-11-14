# 18 — The Admin Account · Design

## Role

`App\Enums\Role` gains `Admin = 'admin'` (label "Admin"). `users.role` is a `string(20)`, so no
migration is needed.

`homeRoute()` gives each role its landing page:

| Role | Lands on |
|---|---|
| Student | `student.requests.create` |
| Registrar | `registrar.evaluation` |
| Department | `department.dashboard` |
| Admin | `admin.dashboard` |

Every `match` over a role gains `Role::Admin`:
- `CredentialRequestPolicy::view` allows it.
- The notification links (`RequestActivity::url`, `DueReminder`) point an admin at
  `records.show`. Admins are not sent request notifications, but a `match` must cover every
  case.

## Routes

| Prefix / name | Middleware | Pages |
|---|---|---|
| `admin` / `admin.` | `role:admin` | `dashboard` (`/admin`), `reports`, `credential-types` (+ `.workflow`), `departments`, `programs`, `purposes`, `users`, `calendar`, `logs` |
| `registrar` / `registrar.` | `role:registrar` | the five queues, `requests.show`; `/registrar` redirects to `registrar.evaluation` |
| `records` / `records` | `role:registrar,admin` | `records` (`/records`), `records.show` (`/records/{id}`) |

`registrar.dashboard`, `registrar.reports`, `registrar.records*` and the old `/registrar/<admin
page>` URLs are gone.

## Components

These classes move from `App\Livewire\Registrar` to `App\Livewire\Admin`, with their views
moving from `livewire/registrar/…` to `livewire/admin/…`:

- `Dashboard`
- `Reports\Index`
- `CredentialTypes\{Index, Workflow}`
- `Departments\Index`
- `Programs\Index`
- `Purposes\Index`
- `Users\Index`
- `Calendar\Index`
- `Logs\Index`

`Registrar\Records\{Index, Show}` stay where they are and are shared. `Records\Show` authorizes
`view` instead of `review`.

`Admin\Dashboard` changes:
- The "Office of the Registrar queue" panel (`officeQueue`) is gone.
- The status tiles link to `records` with a status filter, and "Most overdue" links to
  `records.show`.

The Logs page links a reference no. to `records.show`.

## Policies

| Policy | Allowed |
|---|---|
| `CredentialRequestPolicy::viewAny` (Records, Dashboard, Reports) | Registrar, Admin |
| `CredentialRequestPolicy::view` | Registrar, Admin; an owner; an office the request reached |
| `review`, `evaluate`, `release`, `declareRepresentative` | Registrar (unchanged) |
| `IdentityDocumentPolicy`, `PaymentProofPolicy`, `RequestDocumentPolicy` | Registrar, Admin; the owner |
| `CredentialTypePolicy`, `DepartmentPolicy`, `ProgramPolicy`, `PurposePolicy`, `NonWorkingDayPolicy`, `UserPolicy`, `ActivityLogPolicy` | Admin |

`UserPolicy` keeps its self-checks: no changing your own role or status.

## Accounts

- `StaffForm` accepts Department, Registrar or Admin, and the form gains an "Admin" choice.
- The Change role dialog gains "Admin" ("Manages setup, accounts, the logs, the dashboard and
  reports"). The Registrar's description becomes "Evaluates, accepts and releases requests".
- `ChangeAccountRole` needs no change.

## First Admin

- `config('uphsl.initial_admin.email' / '.password')` read `INITIAL_ADMIN_EMAIL` /
  `INITIAL_ADMIN_PASSWORD`.
- `AdminSeeder` (in `DatabaseSeeder`) creates "System Administrator" when no account has the
  email. Without the email, or without a password while the account is missing, it throws a
  `RuntimeException` naming the setting. It is the only account seeder: `RegistrarSeeder` and
  `config('uphsl.initial_registrar')` were removed on 2026-11-15. Existing registrar accounts
  on the servers stay as they are.
- Deploys never seed. To add the first Admin, set the two lines in `.env` and run
  `php artisan db:seed --class=AdminSeeder --force && php artisan optimize`.
- `app:reset-demo` checks the `INITIAL_ADMIN_*` settings before wiping anything (REQ-18-13).

`DemoSeeder` adds `admin@example.com` and `registrar@example.com` (`DemoSeeder::registrarAccount()`),
and the demo requests are worked by that registrar.
