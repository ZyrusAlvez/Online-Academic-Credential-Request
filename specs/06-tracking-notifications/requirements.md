# 06 — Tracking & Notifications · Requirements

## User stories

- **US-06-1** As a student, I want a tracking history like a parcel tracker, so that I can see
  every hand-off of my document and when it happened.
- **US-06-2** As a student, I want to be notified when my document moves or needs my action,
  so that I don't have to keep checking.
- **US-06-3** As department staff and the registrar, I want to be told when something arrives
  for me.

## Acceptance criteria

### Tracking timeline
- **REQ-06-01** THE SYSTEM SHALL show on the student's request page a tracking history built from
  the ledger, newest first, with date and time, what happened, the office involved and remarks.
- **REQ-06-02** THE SYSTEM SHALL show offices, not individual staff names, in the student's
  tracking history.
- **REQ-06-03** THE SYSTEM SHALL show the registrar the full ledger of a request, including who
  performed each action.
- **REQ-06-04** THE SYSTEM SHALL show department staff the request's history on their request page.

### Student notifications
- **REQ-06-05** WHEN a ledger event is recorded on a student's request THE SYSTEM SHALL notify the
  student in-app, except for actions the student performed (other than the submission
  confirmation) and the internal "payment verified" entry.
- **REQ-06-06** THE SYSTEM SHALL also email the student for key events: submission received,
  payment rejected, approved and routed, signed by an office, put on hold, step skipped,
  ready for release, and cancelled by the registrar.

### Staff notifications
- **REQ-06-07** WHEN a request reaches an office (its step becomes `pending`) THE SYSTEM SHALL notify
  that office's active staff in-app; for the Office of the Registrar, registrar accounts are
  notified too.
- **REQ-06-08** WHEN a request starts waiting for registrar review (free request submitted or
  receipt uploaded) THE SYSTEM SHALL notify active registrar accounts in-app.

### Notification centre
- **REQ-06-09** THE SYSTEM SHALL show a bell with the unread count to every signed-in user, a list
  of recent notifications, and a page with all notifications; opening one marks it read and goes
  to the request; users can mark all as read.
- **REQ-06-10** THE SYSTEM SHALL send notifications through the queue so that actions are not slowed
  down by email delivery.
