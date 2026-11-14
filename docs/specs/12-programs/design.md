# 12 — Programs & Strands · Design

## Data

`programs`: `department_id` (the college, `RESTRICT`), `name`, `is_active`; unique on
(`department_id`, `name`), which also serves the lookup by college. Programs are deactivated, never
deleted. See `docs/data-dictionary.md`.

`users.program` stays a text column holding the chosen name, and `credential_requests.program`
keeps its copy. A foreign key would touch every place that shows a program and rewrite the name on
past requests after a rename; a request must keep what was true when it was made.

## Code

- `Registrar\Programs\Index` (`/registrar/programs`, "Programs" under Administration) follows
  `Registrar\Departments\Index`: `ProgramForm`, `ProgramPolicy`, a modal form, deactivate instead
  of delete.
- `ProgramSeeder` holds the published list by college code and only creates missing rows, so a
  rename or deactivation by the registrar survives. It runs with the base data, on a new or reset
  database (spec 23); deploys do not run it.
- `Student\Profile` loads the chosen college's active programs and clears the program when the
  college changes. `RequesterProfileForm` checks the name with an `exists` rule scoped to the
  college and to active programs.
- No staff account is created automatically (REQ-12-11 superseded, 2026-11-15): the Admin creates
  the Basic Education Department's account like every other one.

## Source

The colleges and their 27 programs are the list the project team supplied on 2026-11-06. It
replaces the earlier 12-college, 34-program list. The colleges are now named as on the
university's site; the list's "category" labels aren't used by the app.

| Code | College | Programs |
|---|---|---|
| `CAS` | College of Arts and Sciences | 3 |
| `SOA` | School of Aviation | 2 |
| `BED` | Basic Education Department | 1 |
| `CBAA` | College of Business Administration and Accountancy | 3 |
| `CCS` | College of Computer Studies | 3 |
| `CCJE` | College of Criminal Justice Education | 1 |
| `CTE` | College of Teacher Education | 5 |
| `CEA` | College of Engineering and Architecture | 4 |
| `GS` | Graduate School | 1 |
| `CNHTM` | College of Nutrition, Hospitality and Tourism Management | 3 |
| `CME` | College of Maritime Education | 1 |

`DepartmentSeeder::COLLEGES` and `ProgramSeeder::PROGRAMS` hold the list.

## Replacing the earlier list (REQ-02-14, REQ-12-12)

*Removed on 2026-11-15 (spec 23): both servers are reset, so nothing is replaced any more and the
seeded list is the 2026-11-06 one. This section is kept as the record of that one-time change.*

The migration `replace_college_list` rewrites existing deployments. Fresh installs already seed
the new list, and the migration then finds nothing to do.

- **Colleges are renamed in place:** CAA → SOA, CBA → CBAA, CRIM → CCJE, COED → CTE, COE → CEA,
  CIHM → CNHTM; CAS, BED, CCS, GS and CME only get their names checked. Requests, requesters,
  route steps and office visits all point at the college rows with `RESTRICT` keys, so a rename
  is the only way to replace a college without losing or rewriting history. Requesters with BS
  Architecture move from SOA to CEA.
- **The College of Law** has no successor. It is deleted, or deactivated while anything still
  refers to it.
- **Programs** are only a pick list (`users.program` and `credential_requests.program` hold text),
  so the earlier rows are deleted and the new list is created. `PROGRAM_RENAMES` maps each
  continuing program's old name to its new one on requesters' profiles. Past requests are left as
  they were.
- **Staff accounts** of every college are deleted, each in its own savepoint. One that a foreign
  key still holds (it acted on a request, or has activity-log entries) is deactivated instead. In
  local and staging the `@example.com` demo accounts are kept and renamed to the new code's
  address, and `DemoSeeder::staffAccount()` creates any that are missing. Nothing is created in
  production.
