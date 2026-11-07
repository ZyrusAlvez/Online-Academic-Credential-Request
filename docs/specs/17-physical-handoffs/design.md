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
- `forwardDocument(OfficeVisit, User)` (new, Done only): closes the visit `completed`
  (`completed_at` = left, `working_days`). A correction is then forwarded back to the step's
  office as a `resubmission`. A route visit `advance()`s to the next waiting step. `forward()`
  still sets `in_progress` when the paper leaves the first office.
- `returnVisit()` stays Received-only (REQ-17-10).
- `nextOffice(CredentialRequest): ?Department` (public, read-only): where the paper goes next.
  For a correction, it is the office that returned it. Otherwise it is the first waiting step
  after the open visit's step, or after the last completed step.

## UI

- `x-check-card`: Confirm is `variant="primary" color="green"` and Reject is
  `variant="primary" color="red"`. The chosen button gets a ring, and the other one is dimmed
  (`opacity-60`) once a result exists.
- Decision row: `justify-center`. The Remarks textarea has no `description`.
- `partials.request-record` takes `'ledger'` in `$hide`. The working page always hides it, and
  the Records page keeps it.
- `partials.visit-actions`: Received → Done button (`:done` label, e.g. "Signed") plus Return.
  Done → "Done :time · Forward to <next>" button with a confirmation. In transit → Accept receipt.
- Registrar In Progress (`StageQueue::InProgress`): adds `accepted_for_processing`. Its columns are
  Request · Requester · Currently at (office, state) · Next · Due. `Queue::forward(int $id)`
  authorizes `act` on the open visit and calls `forwardDocument`. Rows held by the Registrar link
  to `registrar.requests.show`.
- Working page `PANEL_OFFICE`: "Currently at / Next" header, then `partials.visit-actions`.
- `x-office-visits`: columns Office · Received · Done · Left · Time spent · Status. Done and Left
  are hidden below `sm`.
- Every open-visit status list (`openVisit`, `VisitQueue`, `RegistrarRequestFilter::heldBy`,
  dashboards, `SignatoryReport`, `DepartmentReport`, `StageQueue::atFinalCheck`) includes `done`.
  Department "In office" shows `received` and `done`.

## Demo data

`DemoSeeder::advanceUntil` forwards after each Done, so its scenarios end the same way.
