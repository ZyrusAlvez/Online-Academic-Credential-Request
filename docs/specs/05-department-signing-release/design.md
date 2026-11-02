# 05 — Office Routing: Receipt, Release, Returns & Final Check · Design

## Data

`request_steps` (route snapshot): `position`, `department_id`, `task` (`RouteTask` + `final_check`),
`instructions`, `status` (`StepStatus`: `waiting`, `current`, `completed`, `bypassed`),
`completed_at`. Unique `(credential_request_id, position)`.

`office_visits`: one row per physical stay of the document at an office.

| Column | Notes |
|---|---|
| request_step_id, department_id, visit_no | visit_no counts per request and office |
| purpose | `VisitPurpose`: `route`, `correction`, `resubmission` |
| status | `VisitStatus`: `in_transit`, `received`, `completed`, `returned`, `bypassed` |
| from_department_id, forwarded_at, forwarded_by | dispatch |
| received_at, received_by | Accept receipt |
| completed_at, completed_by | approve, return or bypass |
| returned_to_department_id, return_reason, corrective_action, remarks | returns |
| working_days | frozen when the visit closes: working days from receipt to completion |

At most one open visit (in transit or received) per request, enforced under the request lock.
Timestamps are written once.

`delay_notes` (append-only): request, visit (nullable), department, `category` (`DelayCategory`:
the client's nine categories), `reason`, `corrective_action`, `recorded_by`.

`due_date_pauses`: request, `reason`, `resume_status`, `started_at/by`, `ended_at/by`,
`working_days`.

## Workflow

| Method | Effect | Ledger |
|---|---|---|
| `accept` (private, spec 04) | steps + REG final check; step 1 current; visit 1 in transit to its office | accepted |
| `acceptReceipt(visit)` | in_transit → received; request → in_progress / for_final_approval | receipt_accepted |
| `completeVisit(visit)` | route/resubmission visit: step completed, next visit forwarded, or ready_for_release after the final check; correction visit: forwarded back as a resubmission | step_completed / document_resubmitted, document_forwarded |
| `returnVisit(visit, office, reason, action)` | visit returned; correction visit forwarded to the earlier office; request → returned_for_correction | document_returned |
| `recordDelay` | delay note | delay_recorded |
| `bypassStep(reason)` | open visit and step bypassed; next forwarded | step_bypassed |
| `requestCustomerAction` (after acceptance) | pause opened, status → returned_for_customer_action | customer_action_requested |
| `customerResponded` | pause closed, `adjusted_due_at` = effective due + paused working days | customer_responded |

`OfficeVisitPolicy`: `act` for staff of the visit's office, or registrars when it is the Office of
the Registrar; `bypass` for registrars. `App\Queries\VisitQueue` feeds the office queue (tabs:
Incoming, In office, History) and the registrar's Office of the Registrar queue.
