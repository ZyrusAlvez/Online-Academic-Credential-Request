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

`<x-request-tracker :request>` — the requester's progress tracker (*Proposed Workflow*, sample
customer portal): five milestones with done / current / upcoming states, the office holding the
document, and the processing period and expected release date once accepted.

`<x-office-visits :visits>` — the internal monitoring table: office, date received, date
released, working days spent, status (registrar and office pages).

## Notifications

- `App\Listeners\SendRequestNotifications` handles `RequestEventRecorded` (fired after commit):
  - requester — every non-internal event except their own actions (the `submitted` confirmation is
    always sent); email as well for `RequestActivity::MAILED_TO_REQUESTERS` (REQ-06-05);
  - offices — `document_forwarded` and `document_returned` to the destination office's staff
    (registrars for the Office of the Registrar);
  - registrars — `submitted`, `payment_submitted`, `customer_responded`, `representative_declared`.
- `App\Notifications\RequestActivity` (`ShouldQueue`) — `database` always; `mail` for requesters on
  the key event types. Payload: `request_id`, `reference_no`, `title`, `message`, `url`
  (role-specific request page).

## UI

- `App\Livewire\NotificationBell` in the sidebar (desktop) and mobile header: unread count,
  `wire:poll.30s`, dropdown with the 8 latest, "Mark all read", "View all".
- `App\Livewire\Notifications\Index` at `/notifications` — paginated list; `open(id)` marks read
  and redirects to the notification's URL (only URLs on this app are followed).
