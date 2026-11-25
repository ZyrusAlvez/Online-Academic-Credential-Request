# 37 — Deleting Requests · Design

## Schema

Migration `2026_11_25_000002_add_soft_deletes_to_credential_requests` adds to `credential_requests`:

| Column | Type | Null | Description |
|---|---|---|---|
| deleted_at | timestamp | Yes | When the Admin deleted it (Laravel soft delete), indexed |
| deleted_by | bigint unsigned, FK → users (restrict) | Yes | The Admin who deleted it |
| deletion_reason | varchar(500) | Yes | Why |

All three are set on every credential of the application and cleared on restore.

## Hiding (REQ-37-02)

`CredentialRequest` uses `SoftDeletes`, so every Eloquent query on requests, every relation to
them and every `whereHas('credentialRequest')` leaves deleted ones out, and route-model binding
404s them. That covers Records, the Registrar's stage queues (`RegistrarRequestFilter`,
`StageQueue`), the department's open queues (`VisitQueue::open`), reports (`ReportFilter::requests`,
`heldVisits`, `PerformanceMetrics`), dashboards, the Request Tracker, `User::openRequestCount()`,
`SendDueReminders`, walk-in linking and the cashier's list.

Queries that start from office visits without going through the request get
`whereHas('credentialRequest')`: `VisitQueue::history()`, `ReportFilter::visits()` (via
`matching()`), and the department's bulk actions (`Department\Queue::eachSelected`).
`ActivityLogFilter`'s search by requester name uses `withTrashed()` (REQ-37-06).

## Deleting and restoring

- `App\Actions\Requests\DeleteRequest::handle(CredentialRequest, User $admin, string $reason)` and
  `RestoreRequest::handle(CredentialRequest, User $admin)`: in a transaction, set or clear the three
  columns on every credential with the same `application_id` (`withTrashed()`), then write one
  activity-log entry through `RecordActivity` (category Request, `request.deleted` /
  `request.restored`, subject the lead credential, the application's reference number, the reason
  in the description). No status change and no ledger entry: deletion isn't a step of the request.
- `CredentialRequestPolicy`: `delete` (Admin, not deleted), `restore` (Admin, deleted); `view` of a
  deleted request is the Admin's only (others get a 404).
- `routes/web.php`: `records.show` binds `->withTrashed()`; every other route keeps the default.

## Records (Admin)

- `Records\Index`: the status filter gains "Deleted" for the Admin (`status=deleted`), listing only
  deleted applications (`onlyTrashed()`), with the other filters still applied.
- `Records\Show`: loads the application `withTrashed()`. A Delete button and a reason dialog
  (`@can('delete')`); on a deleted request a red callout (when, by whom, reason), a Restore button
  (`@can('restore')`) and no Override.
