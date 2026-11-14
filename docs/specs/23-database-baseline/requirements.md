# 23 — Database Baseline and Reset · Requirements

## Why

The `database/` folder carried the project's history: tables created, later rebuilt, later altered,
plus one-time fixes (replacing the college list, deleting the College of Law, copying data between
shapes). A new or reset database replayed all of it. Before the system goes live both servers are
reset, so the history is no longer needed (user's decision, 2026-11-15).

The rule from now on:

- **Migrations hold the structure.** They run on every deploy, each file once per database, so
  local, staging and production always have the same tables and columns.
- **Seeders hold the starting content.** They run only when a database is created or reset, never
  on a deploy. After that, the Admin changes the lists in the app.

## User stories

- As a developer, I want each table created once in its final shape, so the folder is short and
  shows the real structure.
- As the person who runs the servers, I want one command that resets an environment to a clean
  start, so staging and production can be emptied of test data before going live.

## Acceptance criteria

### Migrations
- **REQ-23-01** THE migrations SHALL build the complete structure on an empty database, creating
  each table once in its final shape. They SHALL NOT add or change any rows, except the fixed
  request statuses (REQ-19-04).

### Base data
- **REQ-23-02** THE normal seeding (`DatabaseSeeder`) SHALL load the base data every environment
  needs: the offices, programs, purposes, credential types with their routes, holidays and the
  first Admin (REQ-18-11). It SHALL NOT create any other account.
- **REQ-23-03** WHEN the base data is seeded again THE SYSTEM SHALL add only what is missing: no
  duplicates, and nothing the Admin changed is overwritten.

### Reset
- **REQ-23-04** WHEN `php artisan app:reset` runs THE SYSTEM SHALL delete every row and every
  uploaded file of that environment, rebuild the structure and load the base data only (no demo
  data).
- **REQ-23-05** IF `INITIAL_ADMIN_EMAIL` or `INITIAL_ADMIN_PASSWORD` is not set THEN `app:reset`
  SHALL refuse to start, changing nothing.
- **REQ-23-06** WHILE the environment is production, `app:reset` SHALL ask the operator to type
  `reset production` and SHALL stop, changing nothing, on any other answer. Local and staging are
  not asked.
- **REQ-23-07** `php artisan app:reset-demo` SHALL do the same reset and then load the demo data
  (REQ-07-09). It SHALL refuse to run in production.
