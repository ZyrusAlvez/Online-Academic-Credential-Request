# 06 — Tracking, Notifications & Reminders · Requirements

Source: *Proposed Workflow*, section VIII-A (customer tracking dashboard and notifications) and X
(automatic reminders).

## User stories

- **US-06-1** As a requester, I want a progress tracker and a history of my request, so that I can
  see where my document is and when it is due.
- **US-06-2** As a requester, I want to be notified when my request is received, needs my action,
  is accepted or is ready, so that I don't have to keep checking.
- **US-06-3** As office staff and the registrar, I want to be told when something arrives for me.

## Acceptance criteria

### Tracking
- **REQ-06-01** THE SYSTEM SHALL show on the requester's request page a progress tracker (submitted
  and evaluated, payment verified, processing, final approval and quality check, ready for release,
  released), the office currently holding the document, and — once accepted — the processing
  period and the expected release date. The tracker SHALL be a step bar: an icon per milestone,
  joined by a line that turns green up to the furthest milestone reached, with the date and time
  under each finished milestone; left to right on wider screens and top to bottom on phones.
- **REQ-06-17** UNDER the step bar THE SYSTEM SHALL list, in order and numbered, every office the
  document goes through (the Dean as "Dean, <college>"), each with its task and ending with the
  Registrar's final quality check; each office SHALL show whether it is done (with the date), the
  current one, or still to come. Before acceptance the list SHALL be the credential's route, all
  still to come; once accepted, the request's own route. A declined or cancelled request SHALL not
  show the list. *(User's request, 2026-11-11.)*
- **REQ-06-02** THE SYSTEM SHALL show the requester a history of their request built from the
  ledger, newest first, naming offices but never individual staff, and leaving out internal
  entries (forwarding, internal returns and delay notes); an internal return SHALL show as
  "In progress".
- **REQ-06-03** THE SYSTEM SHALL show the registrar the full ledger of a request, including who
  performed each action, and the office visits with the date received, date released, working days
  spent and status.
- **REQ-06-04** THE SYSTEM SHALL show office staff the request's office visits and history on their
  request page.

### Requester notifications
- **REQ-06-05** THE SYSTEM SHALL notify the requester in-app and by email when the request is
  received, when it is returned to them with what to fix (REQ-16-13), when payment instructions are
  available, when the payment is rejected, when the payment is verified and the request accepted
  (with the expected release date), when the credential is ready for release, and when the request
  is declined. *(Only the requester cancels now, REQ-16-21, and their own actions are not sent to
  them.)*
- **REQ-06-16** THE SYSTEM SHALL let a requester turn email notifications on or off in Settings →
  Email notifications; they are on for every new and existing account. WHILE they are off THE
  SYSTEM SHALL send no request, reminder or ID-review emails to that requester, and SHALL still
  notify them in-app. Verification codes and password-reset emails are always sent. Staff, who are
  notified in-app only, do not see the setting.
- **REQ-06-06** THE SYSTEM SHALL notify the requester in-app of the other milestones shown in their
  history (evaluation started, compliance recorded, received or completed by an office, representative
  verified), and never about their own actions other than the submission confirmation.

### Staff notifications
- **REQ-06-07** WHEN a document is forwarded or returned to an office THE SYSTEM SHALL notify that
  office's active staff in-app; for the Office of the Registrar, registrar accounts are notified.
- **REQ-06-08** WHEN a request is submitted or resubmitted (REQ-16-15), a proof of payment is
  uploaded, or a representative is declared THE SYSTEM SHALL notify active registrar accounts in-app.

### Reminders
- **REQ-06-11** WHEN a document's effective due date falls within the next working day THE SYSTEM
  SHALL remind the staff of the office holding it, once per office visit.
- **REQ-06-12** WHEN a document becomes overdue THE SYSTEM SHALL alert the staff of the office
  holding it, once per office visit, so an office that receives an overdue document is alerted too.
- **REQ-06-13** WHEN a request becomes overdue THE SYSTEM SHALL tell the requester once, in-app and
  by email.
- **REQ-06-14** THE SYSTEM SHALL send reminders every working day at 07:00, skip non-working days,
  and never send the same reminder twice even when run again.

### Notification centre
- **REQ-06-09** THE SYSTEM SHALL show a bell with the unread count to every signed-in user, a list
  of recent notifications, and a page with all notifications; opening one marks it read and goes
  to its page; users can mark all as read.
- **REQ-06-10** THE SYSTEM SHALL send notifications through the queue so that actions are not slowed
  down by email delivery.
- **REQ-06-15** THE SYSTEM SHALL style every email like its sign-in pages: the UPHSL crest and app
  name in UPHSL navy (`primary` #012265) over a pale-blue (`primary-50`) background, the message
  in a white card with a `secondary` border, navy buttons, and a footer naming the Office of the
  Registrar and saying that credentials are released in person. The crest is a PNG, since email
  clients do not reliably show WebP.
