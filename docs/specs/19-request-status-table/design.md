# 19 — Request Status Table · Design

## Table

`request_statuses` (REQ-19-01):

| Column | Type | Notes |
|---|---|---|
| `value` | `string(30)`, primary key | The stored value, e.g. `pending_compliance` |
| `label` | `string(60)` | What people see, e.g. *Pending compliance* |
| `meaning` | `string(255)` | One sentence, from the status table in `00-overview.md` |
| `sort_order` | `unsignedTinyInteger`, unique | The order of the enum's cases |
| `is_final` | `boolean` | Released, Declined and Cancelled |

There is no model and no screen: the app reads statuses from the enum. The table is reference data
for the database.

## Foreign keys

Every column that stores a request status points at `request_statuses.value` with
`restrictOnUpdate` and `restrictOnDelete` (REQ-19-03):

- `credential_requests.status`
- `request_events.from_status` and `request_events.to_status` (nullable)
- `due_date_pauses.resume_status`

The ledger is append-only, so a status value is never renamed in place: a renamed status would
be a new row, and the old row stays for the events that used it.

## Migration

`2026_11_10_000002_create_request_statuses_table` creates the table, inserts the fourteen rows
from literal values (not from the enum, so the migration keeps meaning the same thing later),
then adds the foreign keys (REQ-19-04). A deploy runs it; no seeder is involved. `down()` drops the
foreign keys and the table.

The existing rows already use only enum values, so the foreign keys apply without a data change.

## Keeping the table and the enum together

`tests/Feature/RequestStatusTableTest.php` compares the rows with `RequestStatus::cases()`:
values, order, labels and final flags (REQ-19-02). Adding a status means an enum case and a
migration that inserts its row; the test fails if either is missing.
