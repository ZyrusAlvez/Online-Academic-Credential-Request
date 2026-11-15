# 24 — Department Pages · Design

## Routes

| Route | Component | Notes |
|---|---|---|
| `department` | redirect | to `department/incoming`, named `department.dashboard`; the role's home (`Role::homeRoute`) is `department.incoming` |
| `department/incoming` | `Department\Queue` (`incoming`) | checkboxes, Acknowledge receipt |
| `department/to-validate` | `Department\Queue` (`to_validate`) | |
| `department/history` | `Department\Queue` (`history`) | |
| `department/{list}/{request}` | `Department\Requests\Show` | `department.incoming.show`, `department.to-validate.show`, `department.history.show` |
| `department/requests/{request}` | redirect | `department.requests.show`, kept for notifications and emails: to the request's list page |

Every route keeps the `role:department` middleware. The lists pass the list as the route's
`queue` default, like the registrar's queues (spec 16).

## Pieces

- `App\Enums\DepartmentQueue` (`incoming`, `to_validate`, `history`): label, description, icon,
  route names, empty text, the default sort direction, and `forRequest($request, $departmentId)`:
  the open visit at the office in transit → Incoming, received or done → To Validate, otherwise
  History. `DepartmentLink::to($request, $departmentId)` turns it into the address.
- `App\Queries\VisitQueue::for($queue, $departmentId, $search, $typeId, $sort, $direction)`
  builds a list's query from the existing `open()` and `history()`, applies
  `RegistrarRequestFilter::matching()` and the credential type on the visit's request, and orders
  by a whitelisted column:
  `request` (the request's `reference_no`), `requester` (`requester_name`), both by subquery, or
  `since` (`forwarded_at`, `received_at` or `completed_at` by list). Direction is `asc` or
  `desc`, anything else the list's default.
- `App\Livewire\Department\Queue`: `#[Locked] $list`; `#[Url]` `search`, `type`, `sort`,
  `direction`; `$selected` (visit ids). `sortBy($column)` flips the direction on the same column.
  Changing the search, filter, sort or page clears the selection. `acknowledge()` (Incoming only,
  else 404) validates `selected` as at most 100 integers, loads those visits of the user's office
  that are in transit, authorizes `OfficeVisitPolicy::act` on each, and calls
  `RequestWorkflow::acceptReceipt` one by one (each is its own transaction); a refused one is
  counted, not fatal.
- Checkboxes are native inputs (`wire:model.live="selected"`, with an `aria-label` naming the
  tracking number); the header one selects or clears the page's rows. The button is disabled on
  the server while `selected` is empty, and asks for confirmation with the app's confirm dialog
  (`data-confirm-*`).
- `Department\Requests\Show`: `mount($credentialRequest, $queue)` authorizes `view` (404 for a
  request never sent to the office) and redirects to `DepartmentLink::to()` when the list differs.
  Its `attempt()` follows the request to its new list after an action, as the registrar's does
  (REQ-16-26). The view: `x-request-details`, `x-payment-proof-list` with the verified proofs,
  `x-request-route`, then the action panel: Acknowledge receipt, or `partials.visit-actions`.
- Sidebar: one item per list; Incoming and To Validate carry their count as the Flux navlist
  badge (`VisitQueue::for(...)->count()`).
- `PaymentProofPolicy::view` also allows department staff for a verified proof whose request has
  a visit at their office; anything else stays 404.

## Removed

- `Department\Dashboard`, its view and `x-visit-queue`.
- Delay reasons: `partials.delay-modal`, `ActsOnVisits::recordDelay` and its properties,
  `RequestWorkflow::recordDelay`, the demo delay note. The `delay_notes` table, `DelayNote`,
  `DelayCategory`, `RequestEventType::DelayRecorded` and the report's reading of recorded
  reasons stay, so earlier notes and ledger entries still show. No migration.
