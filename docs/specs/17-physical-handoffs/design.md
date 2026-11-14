# 17 — Physical Hand-offs and the Stage Page · Design

## Data

`office_visits` gains two nullable columns:

| Column | Type | Notes |
|---|---|---|
| `done_at` | timestamp | when the office's task was done (prepared, signed, stamped, corrected) |
| `done_by` | FK → `users`, restrict | who marked it done |

The meaning of `completed_at` / `completed_by` changes. They now record when the paper **left**
the office (it was forwarded on), or when the final check closed the visit. `working_days` stays
received → left, which is the time the office held the paper. Older visits have no `done_at`,
because they were done and forwarded in the same click.

`App\Enums\VisitStatus` gains `Done = 'done'` ("Done, not yet forwarded", indigo). `isOpen()`
includes it, because the office still holds the paper. A visit runs:

```
in_transit ──Accept receipt──▶ received ──Done──▶ done ──Forward──▶ completed
                                   │                                  (next visit: in_transit)
                                   └──Return for correction──▶ returned (next visit: in_transit)
final check: received ──Approve──▶ completed (ready for release)
```

`App\Enums\RequestEventType` gains `Corrected` ("Corrected"), logged when a correction visit is
marked done. A route visit marked done keeps logging `StepCompleted`. Forwarding logs
`DocumentResubmitted` for a corrected document, then `DocumentForwarded`, which notifies the next
office as before.

## RequestWorkflow

- `accept()`: when the first step's office is the Registrar, `forward()` is followed at once by
  receiving the visit (`received_at` = now, `received_by` = the accepting registrar,
  `ReceiptAccepted` logged).
- `completeVisit()` (Received only):
  - Final check of the route: closes `completed` and `advance()`s to `ready_for_release`, as
    before.
  - Otherwise: `status = done`, `done_at`, `done_by` and `remarks` are set. A route step is marked
    `completed` and `StepCompleted` logged; a correction logs `Corrected`.
- `forwardDocument(OfficeVisit, User)` (new): Done, or Received at the Office of the Registrar for
  its own route task or a correction (not the final check), which is first marked done in the
  same transaction (REQ-17-12). It then closes the visit `completed`
  (`completed_at` = left, `working_days`). A correction is then forwarded back to the step's
  office as a `resubmission`. A route visit `advance()`s to the next waiting step. `forward()`
  still sets `in_progress` when the paper leaves the first office.
- `returnVisit()` stays Received-only (REQ-17-10).
- Where the paper goes next is read, not changed, so it lives on the models:
  `OfficeVisit::nextStep()` gives the office that returned a correction, or else the first
  waiting step after the visit's step. `CredentialRequest::nextOffice()` gives that step's
  office for the open visit. Both read loaded relations. When the next step is in the same
  office, `completeVisit()` moves the paper on at once.

## UI

- `x-check-card`: Confirm is `variant="primary" color="green"` and Reject is
  `variant="primary" color="red"`, with the accent pushed to the 700 shade so white text meets
  4.5:1. The chosen button gets a ring. Reject comes first and Confirm second, so Confirm is on
  the right.
- Decision row: `justify-center`, in the order Decline, Return, Approve. The Remarks textarea has
  no `description`.
- Blocked decisions (REQ-17-17): no button is `disabled`. `Show::approve()`, `returnRequest()`
  and `decline()` gather the reasons before calling `RequestWorkflow`:
  - Approve: the parts that block it come from the stage's parts (`CheckItem::for`) and their
    results, rejected first and then not checked yet. They go in the locked `$blockedParts`
    (`label`, `state`: `rejected` / `unchecked` / `not_entered`), with an official receipt
    number not entered added as `not_entered` (and, for Confirm Compliance, items not received as
    `to_receive`). The dialog shows the rule, that list (✗ rejected, – not checked
    yet / not entered yet) and "To approve, …".
  - Return and Decline: the reasons are sentences in the locked `$blockers`: no rejected part
    for Return at the evaluation, and the validation messages (remarks), with friendly wording.
    Any other validation message for Approve (e.g. remarks too long) goes there too.
  - The validation error is still thrown, so the field is marked too.

  Any reason fills `$blockedAction` and opens the `decision-blocked` Flux modal, with one OK
  button. `RequestWorkflow` keeps its own guards; this only explains them earlier.
- `partials.request-record` takes `'ledger'` in `$hide`. The working page always hides it, and
  the Records page keeps it.
- `partials.visit-actions`: Received → Done button (`:done` label, e.g. "Signed") plus Return.
  Done → "Done :time. Forward it to <next> from In Progress." with no button
  for a registrar, with a link to In Progress. Department staff get the "Forward to <next>"
  button, which calls `ActsOnVisits::forwardDocument` (refused for registrars, REQ-17-16). In
  transit → Accept receipt.
- Registrar In Progress (`StageQueue::InProgress`): adds `accepted_for_processing`. Its columns are
  Request · Requester · Currently at (office, state) · Next · Due. `Queue::forward(int $id)`
  authorizes the new `OfficeVisitPolicy::forward` and calls `forwardDocument`. The policy
  requires an actionable visit that `OfficeVisit::isForwardable()`, held by the user's office,
  where a registrar's office is the Office of the Registrar. The row's button shows for
  forwardable visits the Office of the Registrar holds. Rows held by the Registrar link to `registrar.requests.show`.
- Working page `PANEL_OFFICE`: "Currently at / Next" header, then `partials.visit-actions`.
- `x-office-visits`: columns Office · Received · Done · Left · Time spent · Status. Done and Left
  are hidden below `sm`.
- Every open-visit status list (`openVisit`, `VisitQueue`, `RegistrarRequestFilter::heldBy`,
  dashboards, `SignatoryReport`, `DepartmentReport`, `StageQueue::atFinalCheck`) includes `done`.
  Department "In office" shows `received` and `done`.

## Who holds the paper (REQ-17-18)

The data already records it: an `in_transit` visit has `from_department_id`, the office that
forwarded it. That column is null only for the forward made at acceptance, which leaves the
Office of the Registrar. No migration is needed. The rule lives in one place:

- `Department::registrar()` finds the Office of the Registrar by `REGISTRAR_CODE`. It is not
  memoized: tests share a process while ids change.
- `OfficeVisit::holder()` returns `fromDepartment ?? Department::registrar()` for `in_transit`,
  and `department` otherwise.
- `OfficeVisit::whereHeldBy($query, $id)` matches in-office visits (`received`, `done`) at
  `department_id`, or `in_transit` visits whose `from_department_id` is `$id`. When `$id` is
  the Registrar, a null `from_department_id` matches too.
- `OfficeVisit::holderSql()` is the same rule as a SQL `CASE`, with its bindings, for group-by
  counts.
- `CredentialRequest::nextOffice()` returns the visit's own office for an `in_transit` open
  visit, and `nextStep()`'s office otherwise. `whereabouts()` names `holder()`.

Using it:

- `RegistrarRequestFilter::heldBy` (In Progress and Records filters) is `whereHas('visits',
  OfficeVisit::whereHeldBy)`.
- The dashboard's `waitingByOffice` and `DepartmentReport`'s pending count group by
  `holderSql()`. `SignatoryReport` lists a visit under `holder()`.
- In Progress and the working page show `holder()->name`. An `in_transit` state reads
  "Forwarded :time to :office, not yet received".
- The In Progress row links to the working page when the open visit's office is the Registrar
  (it accepts receipt or forwards there).
- The receiving office's Incoming tab, Accept receipt, the Registrar's own office queue and
  the office visits table are not changed. They are to-do lists and history, not "where is it".

## Demo data

`DemoSeeder::advanceUntil` forwards after each Done, so its scenarios end the same way.
