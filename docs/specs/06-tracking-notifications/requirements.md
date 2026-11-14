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
  and evaluated, payment verified, then each office that processes it, ending with the final quality
  check (REQ-06-17), then ready for release or released; the sample portal's "processing" and "final
  approval and quality check" are shown as those offices, by the user's decision of 2026-11-12),
  the office currently holding the document, and — once accepted — the processing
  period and the expected release date. The tracker SHALL be a step bar: an icon per milestone,
  joined by a line that turns green up to the furthest milestone reached, with the date and time
  under each finished milestone; left to right on wider screens and top to bottom on phones.
- **REQ-06-17** THE step bar SHALL have one milestone per office the document goes through, in
  order, between "payment verified" and "ready for release" (the Dean as "Dean, <college>"), each
  with its task and ending with the Registrar's final quality check, so the whole process reads as
  one line with no separate list of offices. Each office SHALL show whether it is done (with the
  date), the current one, or still to come. Before acceptance they SHALL be the credential's route,
  all still to come; once accepted, the request's own route. On a declined or cancelled request no
  office SHALL be current. *(User's request, 2026-11-11; one bar instead of a list under it,
  2026-11-12.)*
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
- **REQ-06-16** THE SYSTEM SHALL let requesters, registrars and department staff turn email
  notifications on or off in Settings → Email notifications; they are on for every new and
  existing account. WHILE they are off THE SYSTEM SHALL send that account no informational request
  or reminder emails, SHALL still send [ACTION REQUIRED] emails (REQ-06-18), and SHALL still notify
  in-app; the requester's page SHALL say that [ACTION REQUIRED] notifications are always emailed.
  Verification codes and password-reset emails are always sent. Admins, who are not notified, do
  not see the setting. *(Staff and the [ACTION REQUIRED] exception: user's decision, 2026-11-12.)*
- **REQ-06-06** THE SYSTEM SHALL notify the requester in-app and by email of the other milestones
  shown in their history (evaluation started, compliance recorded, received or completed by an
  office, representative verified), and never about their own actions other than the submission
  confirmation. *(By email too: user's decision, 2026-11-12.)*
- **REQ-06-18** THE SYSTEM SHALL mark a requester notification [ACTION REQUIRED] when they must act:
  returned for clarification, office visit required (pending compliance), valid ID not accepted,
  payment instructions (an amount is due), payment not accepted, representative not accepted, and
  ready for release (claim it). Its email subject SHALL start with "[ACTION REQUIRED]", and the
  bell and the Notifications page SHALL show it with an "Action required" badge.
  *(User's request, 2026-11-12.)*

### Staff notifications
- **REQ-06-07** WHEN a document is forwarded or returned to an office THE SYSTEM SHALL notify that
  office's active staff in-app and by email; for the Office of the Registrar, registrar accounts
  are notified. *(By email too: user's decision, 2026-11-12.)*
- **REQ-06-08** WHEN a request is submitted or resubmitted (REQ-16-15), a proof of payment or
  another valid ID is uploaded, the requester replies, or a representative is declared THE SYSTEM
  SHALL notify active registrar accounts in-app and by email. *(By email too: user's decision,
  2026-11-12.)*
- **REQ-06-19** A staff notification SHALL be sent to each account separately, so each account has
  its own read status, and its email SHALL name the request, the credential and the requester,
  say what happened and where the document is, and link to the staff member's own request page.
  *(User's request, 2026-11-12.)*

### Reminders
- **REQ-06-11** WHEN a document's effective due date falls within the next working day THE SYSTEM
  SHALL remind the department staff of the office holding it, once per office visit, in-app and by
  email. *(Registrars get REQ-06-20 instead; email: user's decision, 2026-11-12.)*
- **REQ-06-12** WHEN a document becomes overdue THE SYSTEM SHALL alert the department staff of the
  office holding it, once per office visit, so an office that receives an overdue document is
  alerted too, in-app and by email. *(Registrars get REQ-06-20 instead; email: user's decision,
  2026-11-12.)*
- **REQ-06-20** WHEN any request being processed is due within the next working day, and again when
  it becomes overdue, THE SYSTEM SHALL alert every active registrar account once per request,
  in-app and by email, naming where the document is. *(User's request, 2026-11-12.)*
- **REQ-06-13** WHEN a request becomes overdue THE SYSTEM SHALL tell the requester once, in-app and
  by email.
- **REQ-06-14** THE SYSTEM SHALL send reminders every working day at 07:00, skip non-working days,
  and never send the same reminder twice even when run again.

### Notification centre
- **REQ-06-09** THE SYSTEM SHALL show a bell with the unread count to every signed-in user, a list
  of recent notifications, and a page with all notifications; opening one marks it read and goes
  to its page; users can mark all as read.
- **REQ-06-10** THE SYSTEM SHALL send notifications (in-app and email) right after the response to
  the action that caused them, so that actions are not slowed down by email delivery and no
  background worker has to run on the server. A notification that fails to send SHALL be logged
  and SHALL NOT stop the others. *(User's decision, 2026-11-13: staging never had a queue worker,
  so queued notifications were never delivered.)*
- **REQ-06-15** THE SYSTEM SHALL style every email like its sign-in pages: the UPHSL crest and app
  name in UPHSL navy (`primary` #012265) over a pale-blue (`primary-50`) background, the message
  in a white card with a `secondary` border, navy buttons, and a footer naming the Office of the
  Registrar and saying that credentials are released in person. The crest is a PNG, since email
  clients do not reliably show WebP.
