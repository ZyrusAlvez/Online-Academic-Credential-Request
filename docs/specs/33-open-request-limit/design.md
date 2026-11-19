# 33 — Open Request Limit · Design

## Where the count lives
- `CredentialRequest::OPEN_LIMIT = 5`.
- `User::openRequestRoom(): int` — `max(0, OPEN_LIMIT − open online requests)`, where open means
  `status` not final (`RequestStatus::isFinal()`) and `channel = online` (REQ-33-01).

## Where it is enforced
The limit is a rule of the requester's New Request screen, not of `RequestWorkflow::submit()`:
the workflow also serves the demo seeders and the tests, which submit many requests for one
account, and walk-ins go through `submitWalkIn()` anyway (REQ-33-05).

`App\Livewire\Student\Requests\Create`:
- `extraChecks()` adds the limit check, so Review shows it with the other errors in the errors
  modal (REQ-33-02). `Edit` overrides `extraChecks()`, so it is not limited (REQ-33-05).
- `submit()` checks again inside its transaction after `User::lockForUpdate()` on the owner's
  row, so two submissions from the same account are serialised (REQ-33-03). The failure is a
  `ValidationException` on `form.credential_type_id`, rolled back like any other.
- `addCredential()` adds a credential only while the form holds fewer than the room left; the
  view's "Add another credential" button follows the same number (`room`, REQ-33-04).
- The view shows a callout (`data-request-limit`) above the form when the room is 0.

## Messages
- Full: "You have 5 open requests, the most allowed at a time. You can request again once one is
  released, declined or cancelled."
- Too many in the form: "You can request :n more credential(s) while you have :open open requests
  (at most 5 at a time)."
