# 32 — The Registrar Carries the Papers · Design

No schema change: `office_visits` already has `returned_to_department_id`, `return_reason`,
`corrective_action`, and `status` is a string.

## Workflow (`RequestWorkflow`)

- `returnVisit()` validates as before (received, not a correction visit, an earlier completed
  office), then sets the visit **Done** with `done_at`, `done_by`, `returned_to_department_id`,
  `return_reason`, `corrective_action` and `remarks`, without `markDone()`, so the step stays
  current. It writes `DocumentReturned` and sets `returned_for_correction` as before. Nothing is
  forwarded yet (REQ-32-07).
- `forwardDocument()`: a Done visit with `returned_to_department_id` (`OfficeVisit::isPendingReturn()`)
  is closed as `Returned` and a `Correction` visit is forwarded to the office it was returned to,
  from the returning office (REQ-32-08). Any other forwardable visit goes on as before
  (`markDone` for the Registrar's own received task, then `leave`).
- The service does not check roles; `OfficeVisitPolicy::forward` does (REQ-32-04).

## Model

- `OfficeVisit::isPendingReturn()`: Done and `returned_to_department_id` set.
- `OfficeVisit::nextStep()`: for a pending return, the earlier completed step of the office it was
  returned to, so `CredentialRequest::nextOffice()` names it.
- `OfficeVisit::scopeForwardable()` (static `whereForwardable`): the SQL twin of `isForwardable()`:
  Done, or Received at the Office of the Registrar for a correction or a route task other than the
  final check.

## Policy

`OfficeVisitPolicy::forward`: registrar role, the visit open on a request being processed, and
`isForwardable()`, for any office. `act` is unchanged (department staff receive, approve, return).

## Registrar

- `RegistrarQueue::Outgoing` (`outgoing`, "Outgoing", `arrow-up-tray`, `registrar.outgoing`), after
  In Progress; no working page (rows open the record), like In Progress. `forRequest()` maps a
  processing request whose open visit is forwardable to Outgoing.
- `StageQueue`: Outgoing = processing requests with an open forwardable visit; In Progress leaves
  them out; the office filter is shared.
- `Registrar\Queue`: `forward(requestId)` and `forwardSelected()` on Outgoing only; `acceptReceipt`
  stays on In Progress. Selection like the department's Incoming (`selected`, `togglePage`).
- View: Outgoing columns are a checkbox, Request, Requester, At (holder, "Done <time>"/"Received
  <time>"), Next (the Forward button) and Due.

## Department

- `DepartmentQueue::ToForward` becomes `Outgoing` (`outgoing`, "Outgoing", route
  `department.outgoing`); checkboxes on Incoming only. `routes/web.php` redirects
  `/department/to-forward[/…]` to `/department/outgoing[/…]`.
- `Department\Queue`: `forward()` and `forwardOne()` removed. The view shows Next as text:
  "<next office>" or "Return to <office>".
- `ActsOnVisits::forwardDocument()` removed (no page calls it).

## Demo data

`DemoRequestSeeder` forwards as the registrar; a returned request is forwarded after the return so
the demo states stay the same.
