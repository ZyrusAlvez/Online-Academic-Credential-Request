# 19 — Request Status Table · Requirements

Source: *Proposed Workflow*, section V, "Recommended request statuses", and the customer
dashboard list in section VIII.

Decided by the user on 2026-11-10: every request status the system can use is listed in its own
database table, so the full set can be seen in the database. It is not shown as a page in the app.

The statuses stay the backed enum `App\Enums\RequestStatus`, which decides what each status does
(buttons, permissions, transitions). The table is the list the database holds the requests to.

## User stories

- **US-19-1** As the project owner, I want every request status listed in one database table with
  its label and meaning, so that I can see all the statuses a request can have.
- **US-19-2** As the project owner, I want the database to refuse a status that is not in that
  table, so that the table is always the complete list.

## Acceptance criteria

- **REQ-19-01** THE SYSTEM SHALL keep a `request_statuses` table with one row per request status:
  its stored value, its label, its meaning, its order and whether it is final (a request in a
  final status cannot move again).
- **REQ-19-02** THE SYSTEM SHALL list in `request_statuses` exactly the statuses of
  `App\Enums\RequestStatus`, in the enum's order, with the enum's labels and final flags.
- **REQ-19-03** WHEN a request, a ledger event (its from and to statuses) or a due-date pause (its
  resume status) is stored with a status that is not in `request_statuses` THE SYSTEM SHALL refuse
  it.
- **REQ-19-04** THE SYSTEM SHALL create the table and its rows in a migration, so that every
  environment has them after a deploy, without seeding.
