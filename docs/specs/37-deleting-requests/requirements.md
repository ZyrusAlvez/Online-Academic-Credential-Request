# 37 — Deleting Requests · Requirements

The Admin removes unwanted requests (test, duplicate or spam) without losing them: a deleted
request is hidden everywhere but kept, and can be restored. Nothing is erased: its ledger, files
and office visits stay as they are. *(User's request, 2026-11-25.)*

## User stories

- **US-37-1** As the Admin, I want to delete a request that shouldn't be in the system, so that it
  no longer shows in anyone's lists, queues or reports.
- **US-37-2** As the Admin, I want to see deleted requests and restore one deleted by mistake.

## Acceptance criteria

### Deleting
- **REQ-37-01** THE Admin SHALL be able to delete any request, in any status, from its record in
  Records, giving a reason (required, up to 500 characters). It deletes the whole application:
  every credential requested together (REQ-27-08). No one else can delete or restore a request.
- **REQ-37-02** WHILE a request is deleted THE SYSTEM SHALL leave it out of every list, queue,
  count, report, dashboard and export, the requester's Request Tracker, notifications and
  reminders, and the open-request limit (REQ-33); its pages SHALL be "not found" to everyone but
  the Admin.
- **REQ-37-03** Deleting SHALL keep the request, its ledger (REQ-04-17), files, office visits and
  everything else as they are, change no status, and notify no one.

### Seeing and restoring
- **REQ-37-04** THE Admin's Records SHALL offer a "Deleted" filter listing only deleted requests;
  opening one SHALL show its record with when, by whom and why it was deleted.
- **REQ-37-05** THE Admin SHALL be able to restore a deleted request, which brings it back
  everywhere exactly as it was.

### Audit
- **REQ-37-06** Every deletion and restoration SHALL be recorded in the activity log (spec 14) with
  the request's reference number, the Admin and the reason; the log keeps finding the request's
  entries while it is deleted.
