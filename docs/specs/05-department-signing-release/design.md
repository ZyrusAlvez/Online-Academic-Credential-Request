# 05 — Department Signing, Hold, Skip & Release · Design

## Service (`App\Services\RequestWorkflow`)

| Method | From | To |
|---|---|---|
| `sign(step, actor, ?remarks)` | step `pending`/`on_hold`, request `in_progress` | step `signed`; next `waiting` → `pending`, or request → `ready_for_release` |
| `hold(step, actor, remarks)` | step `pending` | step `on_hold` |
| `resume(step, actor)` | step `on_hold` | step `pending` |
| `skip(step, registrar, reason)` | step `pending`/`on_hold` | step `skipped`; advance as sign |
| `release(request, registrar)` | request `ready_for_release` | `released` |

Each locks the request row, then re-reads the step, so concurrent actions serialise and the
loser sees a non-current step (`InvalidTransition`). Step events carry `request_step_id` and
`department_id`; request status columns stay null unless the request status changed.

## Authorization

`App\Policies\RequestStepPolicy`
- `act(user, step)`: step is current, request `in_progress`, and either
  `user.department_id === step.department_id` or (user is registrar and step department is `REG`).
- `skip(user, step)`: registrar and step is current.

`CredentialRequestPolicy::view` for department staff already requires a step for their department.
`CredentialRequestPolicy::release`: registrar and request `ready_for_release`.

## UI

- `Department\Dashboard` — tabs: For signature · On hold · History (paginated). Rows link to
  `Department\Requests\Show`.
- `Department\Requests\Show` (`/department/requests/{reference}`) — details, signing route,
  action panel when the current step is theirs: Sign (optional remarks), Put on hold (remarks
  required), Lift hold.
- Shared `App\Livewire\Concerns\ActsOnSteps` trait gives the sign/hold/resume actions to both
  the department page and the registrar's request page.
- `Registrar\Requests\Show` adds: act on a current `REG` step, Skip current step (reason),
  Mark as released.
- `Registrar\Dashboard` — "Office of the Registrar" queue of current `REG` steps (spec 07
  adds statistics around it).
