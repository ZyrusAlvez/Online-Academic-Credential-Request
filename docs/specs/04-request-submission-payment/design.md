# 04 — Submission, Evaluation, Compliance, Payment & Acceptance · Design

The request tables are rebuilt (existing requests are reset, decided with the client): a migration
drops `request_events`, `payment_proofs`, `request_steps` and `credential_requests`, removes stored
and queued request notifications, and creates the tables below. It never throws; `down()` is a no-op.

## Data

`credential_requests`

| Column | Notes |
|---|---|
| reference_no | `CR-YYYY-NNNNNN` tracking number |
| user_id | owner (requester) |
| credential_type_id, copies, purpose, receiving_institution | |
| college_id, academic_status, program, student_number, attended_from, attended_to | snapshot of the profile |
| requester_mode | `personal` or `representative` (spec 11) |
| requires_payment, fee, amount_due | snapshot; amount due = fee × copies |
| status | `App\Enums\RequestStatus` (14 values, see overview) |
| submitted_at | |
| evaluation_started_at, evaluated_by | the evaluator who started it |
| requirements_completed_at | |
| declined_at, decline_reason | |
| accepted_at, accepted_by | processing clock starts |
| due_at | never changed (spec 10) |
| adjusted_due_at | after approved pauses (spec 05) |
| effective_due_at | stored generated `COALESCE(adjusted_due_at, due_at)`, indexed with status |
| ready_at, released_at, processing_working_days | spec 05/11 |
| cancelled_at, cancel_reason | |

`request_deficiencies`: request, `description`, `recorded_by`, `resolved_at`, `resolved_by`,
`resolution` (what was received / clearance status).

`payment_proofs`: as before plus `paid_on` (date) and `official_receipt_no` (recorded on
verification); `PaymentStatus`: `submitted`, `verified`, `rejected`.

## Workflow (`App\Services\RequestWorkflow`)

| Method | From → To | Ledger |
|---|---|---|
| `submit` | — → submitted | submitted |
| `startEvaluation` | submitted → under_evaluation | evaluation_started |
| `requestCompliance(list)` | under_evaluation → pending_compliance | compliance_requested |
| `resolveDeficiency` | pending_compliance (stays) | deficiency_resolved |
| `requestCustomerAction(msg)` | under_evaluation → returned_for_customer_action | customer_action_requested |
| `customerResponded` | returned_for_customer_action → previous | customer_responded |
| `completeEvaluation` | under_evaluation, pending_compliance (all resolved) → awaiting_payment, or accepted if free | requirements_completed (+ accepted) |
| `decline(reason)` | any status before acceptance → declined | declined |
| `submitPaymentProof` | awaiting_payment → payment_verification | payment_submitted |
| `rejectPayment` | payment_verification → awaiting_payment | payment_rejected |
| `verifyPaymentAndAccept(or)` | payment_verification → accepted_for_processing | payment_verified, accepted |
| `changeCollege` | before acceptance | college_changed |
| `cancel` | owner: before acceptance; registrar: any open | cancelled |

`completeEvaluation` requires the owner's identity to be verified (REQ-04-07). The previous status
for `customerResponded` is kept on the event that opened the request for customer action
(`to_status`/`from_status`), and, after acceptance, on the due-date pause (spec 05).

## Screens

- Requester: the new-request form shows each credential's details and the portal notice, takes
  academic details from the profile (edited in My Profile) and asks for copies, purpose, receiving
  school and representative (spec 11). The request page shows the tracker (spec 06), the
  compliance notice and deficiencies, the clarification message and reply form, and the payment
  section.
- Registrar request page: an action panel per status (start evaluation; evaluation outcomes with a
  requirements checklist; record compliance per deficiency; verify payment & accept / reject;
  resume after customer action), the requester's identity with verify/reject, and cancel.
