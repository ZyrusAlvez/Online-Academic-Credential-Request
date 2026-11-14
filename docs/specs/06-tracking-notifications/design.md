# 06 — Tracking, Notifications & Reminders · Design

## Wording in one place

`RequestEvent::summary()` turns a ledger row into a sentence ("Received by the Office of the
Chancellor", "Signed by the Office of the Chancellor", "Ready for release at the Office of the
Registrar"). The timeline, in-app notifications and emails all use it, so the wording never
diverges. `RequestEventType::isInternal()` marks the entries requesters never see (forwarding,
internal returns and resubmissions, delay notes, bypasses).

## Timeline

`<x-request-timeline :events :show-actors>` — vertical list, newest first. Each item: time,
summary, remarks, and (registrar only) the actor's name and role. Requester pages pass only
non-internal events; only the registrar passes `show-actors`.

`<x-request-tracker :request :events>` — the requester's progress tracker (*Proposed Workflow*, sample
customer portal): submitted and evaluated, payment verified, one milestone per office from
`App\Support\OfficeRoute::forRequest()` (REQ-06-17), and ready for release / released, each done /
current / upcoming, plus the processing period and expected release date once accepted. It is
drawn as one step bar (like a parcel tracker): an outlined icon circle per milestone (green when
done, filled primary when current, grey when upcoming; offices use `building-office-2`, the final
quality check `shield-check`), a line between circles that is green once the next milestone is
reached, and the date under each finished milestone. A row on `md` and up (about seven
milestones), a column below. Below the bar, a panel names the current milestone; for an office it
says where the paper physically is, from `whereabouts()` ("Currently with …", REQ-17-18). Dates come from
`requirements_completed_at`, `accepted_at`, each step's `completed_at`, and `released_at`. A
request declined at the final quality check keeps that step current, so on a declined or
cancelled request a current office shows as still to come.

`<x-office-visits :visits>` — the internal monitoring table: office, date received, date
released, working days spent, status (registrar and office pages).

## Notifications

- `App\Listeners\SendRequestNotifications` handles `RequestEventRecorded` (fired after commit):
  - requester — every non-internal event except their own actions (the `submitted` confirmation is
    always sent) (REQ-06-05, 06);
  - offices — `document_forwarded` (a hand-off, or a return for correction, which forwards with
    `VisitPurpose::Correction`) to the destination office's staff (registrars for the Office of
    the Registrar) (REQ-06-07);
  - registrars — `submitted`, `resubmitted`, `payment_submitted`, `identity_resubmitted`,
    `customer_responded`, `representative_declared`, when done by the requester (REQ-06-08).
- `App\Notifications\RequestActivity` (`ShouldQueue`) — `database` always; `mail` when the
  notification is [ACTION REQUIRED] or the account wants email (REQ-06-16). Each recipient gets
  its own `notifications` row, so read status is per account (REQ-06-19). Payload: `request_id`,
  `reference_no`, `event_type`, `title`, `message`, `url` (role-specific request page) and
  `action_required`.
- Delivery (REQ-06-10): `RequestActivity` and `DueReminder` implement `ShouldQueue`, and the
  default queue connection is Laravel's `deferred` driver (`config/queue.php`, not read from
  `.env`). Each notification runs in the same PHP process after the response has been sent (or,
  for `app:send-due-reminders`, after the command finishes), so there is no worker and no `jobs`
  rows. Each send is wrapped in `rescue()`: a Gmail failure is logged, the rest still go out, and
  nothing is retried. Tests use `sync` (`tests/TestCase.php`).
- [ACTION REQUIRED] (REQ-06-18): `RequestEventType::requiresRequesterAction($request)` —
  `customer_action_requested`, `compliance_requested`, `identity_rejected`,
  `requirements_completed` with an amount due, `payment_rejected`, `representative_rejected`,
  `ready_for_release`. Only for the requester. The email subject is
  `[ACTION REQUIRED] <reference>: <title>`.
- Staff emails (REQ-06-19): subject `<reference>: <title>`, the credential and requester, the
  summary and remarks, `staffWhereabouts()`, and an "Open request" button to the role's page.

| Event | Requester | Registrars | Office staff |
|---|---|---|---|
| Submitted, resubmitted, proof of payment, another valid ID, reply, representative declared | only the `submitted` confirmation | in-app + email | — |
| Returned for clarification, office visit, ID or payment or representative not accepted, payment instructions, ready for release | [ACTION REQUIRED], always emailed | — | — |
| Any other visible update | in-app + email | — | — |
| Forwarded or returned to an office | — | Office of the Registrar | in-app + email |
| Due within a working day / overdue | overdue, once | every request, once per kind | holding office, once per visit |

## Email preference (REQ-06-16)

- `users.email_notifications` boolean, default `true` (so existing accounts keep getting email).
  Not mass assignable; written only by `App\Actions\Account\SetEmailNotifications`.
- `User::wantsEmailNotifications()` — `RequestActivity` and `DueReminder` add `mail` to `via()`
  only when it is true, except [ACTION REQUIRED] notifications. `VerificationCode` and password
  reset ignore it.
- `App\Livewire\Notifications\EmailSettings` at `/settings/notifications`
  (`settings.notifications`, `role:student,registrar,department`) — one `flux:switch` inside the
  settings layout, saved as soon as it is flipped; for requesters its description says
  [ACTION REQUIRED] notifications are always emailed. The settings nav shows the link to those
  three roles.

## UI

- `App\Livewire\NotificationBell` in the sidebar (desktop) and mobile header: unread count,
  `wire:poll.30s`, dropdown with the 8 latest, "Mark all read", "View all".
- `App\Livewire\Notifications\Index` at `/notifications` — paginated list; `open(id)` marks read
  and redirects to the notification's URL (only URLs on this app are followed).

## Reminders

`app:send-due-reminders` (`App\Console\Commands\SendDueReminders`), scheduled in
`routes/console.php` daily at 07:00 Asia/Manila; it exits on non-working days (spec 10).

- Due soon: requests being processed whose `effective_due_at` is not past and falls on or before
  the end of the next working day → `DueReminder` to the holding office's department staff
  (`office_due_soon`) and to every registrar (`registrar_due_soon`, REQ-06-20).
- Overdue: requests being processed past `effective_due_at` → `DueReminder` to the holding office's
  department staff (`office_overdue`), every registrar (`registrar_overdue`) and the requester
  (`requester_overdue`).
- Holding office = the open visit's office. Registrars are left out of the office reminder, so the
  Office of the Registrar's own papers are not reminded twice.
- Staff get them in-app and by email (unless switched off); the staff email says where the
  document is (`staffWhereabouts()`) and links to the role's request page.
- `reminder_logs` (`kind`, `subject_id`, `credential_request_id`, `created_at`, unique
  `(kind, subject_id)`): the subject is the office visit for office reminders and the request for
  the registrar and requester reminders. A row is inserted before sending (`insertOrIgnore`), so a rerun sends
  nothing new. Reminders are not ledger events: they change nothing.
