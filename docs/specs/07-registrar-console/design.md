# 07 — Registrar Console & Monitoring · Design

## Data

New migration: `credential_requests.due_at` (timestamp null) + index `(status, due_at)`.
`RequestWorkflow::approve()` sets `due_at` with the working calendar (spec 10).
`CredentialRequest::isOverdue()` and `scopeOverdue()` (`in_progress` and `due_at < now()`).

## Dashboard (`Registrar\Dashboard`)

- Stat tiles (not charts): Needs review, In progress, Overdue, Ready for release; this month's
  submitted / released; average turnaround.
- "Where requests are waiting": one-series horizontal bar list per office — single hue
  (`primary`), value labelled in ink beside each bar, office name as the row label, no legend.
  The labels carry all values, so it doubles as its own table.
- Most overdue (5), and the Office of the Registrar queue from spec 05.
- All aggregates are single grouped queries (no per-row queries).

## Request list

- New filters: `office` (department id of the current step), `overdue` status option.
- `RequestsCsvController` (`/registrar/requests/export`) — reuses the same filter query
  (`App\Queries\RegistrarRequestFilter`) as the Livewire list, streams CSV with `cursor()`.

## Demo seeder

`Database\Seeders\DemoSeeder` — refuses in production. Creates `student@example.com`,
one staff account per department (`<code>@uphsl.edu.ph`, e.g. `lib@uphsl.edu.ph`), and ~12
requests driven through `RequestWorkflow` into every status. Password: `password`.
