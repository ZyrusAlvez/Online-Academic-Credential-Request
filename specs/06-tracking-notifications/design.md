# 06 — Tracking & Notifications · Design

## Wording in one place

`RequestEvent::summary()` turns a ledger row into a sentence ("Signed by Library",
"On hold at Library", "Ready for release at the Office of the Registrar"). The timeline,
in-app notifications and emails all use it, so the wording never diverges.

## Timeline

`<x-request-timeline :events :show-actors>` — vertical list, newest first. Each item: time,
summary, remarks, and (registrar only) the actor's name and role. Student, department and
registrar request pages include it; only the registrar passes `show-actors`.

## Notifications

- `App\Listeners\SendRequestNotifications` handles `RequestEventRecorded` (fired after commit).
  It decides recipients:
  - student — every event except `payment_submitted`, `payment_accepted`, and events whose actor
    is the student (the `submitted` confirmation is always sent);
  - department staff — when the request's current step became `pending` through this event
    (`routed`, `step_signed`, `step_skipped`, `step_resumed` excluded since it was already theirs);
    REG arrivals also go to registrar accounts;
  - registrars — `submitted` with status `registrar_review`, and `payment_submitted`.
- `App\Notifications\RequestActivity` (`ShouldQueue`) — `database` always; `mail` for students on
  the key event types. Payload: `request_id`, `reference_no`, `title`, `message`, `url`
  (role-specific request page).
- Database notifications table (`php artisan make:notifications-table`).

## UI

- `App\Livewire\NotificationBell` in the sidebar (desktop) and mobile header: unread count,
  `wire:poll.30s`, dropdown with the 8 latest, "Mark all read", "View all".
- `App\Livewire\Notifications\Index` at `/notifications` — paginated list; `open(id)` marks read
  and redirects to the notification's URL (only URLs on this app are followed).
