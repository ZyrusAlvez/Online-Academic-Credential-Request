# 04 — Submission, Evaluation, Compliance, Payment & Acceptance · Design

The request tables are rebuilt (existing requests are reset, decided with the client): a migration
drops `request_events`, `payment_proofs`, `request_steps` and `credential_requests`, removes stored
and queued request notifications, and creates the tables below. It never throws; `down()` is a no-op.

## Data

`credential_requests`

| Column | Notes |
|---|---|
| reference_no | `CRYY-NNNNN` tracking number (`CredentialRequest::trackingNumber()`): the id, at least 5 digits, more past 99999; older requests keep `CR-YYYY-NNNNNN` |
| user_id | owner (requester) |
| credential_type_id, purpose, receiving_institution | one document per request (no copies) |
| college_id, academic_status, program, student_number, attended_from, attended_to | snapshot of the profile |
| requester_mode | `personal` or `representative` (spec 11) |
| requires_payment, fee, amount_due | snapshot; amount due = the fee |
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
  academic details from the profile (edited in My Profile) and asks for the purpose, receiving
  school and representative (spec 11). The request page shows the tracker (spec 06), the
  compliance notice and deficiencies, the clarification message and reply form, and the payment
  section.
- Registrar request page: an action panel per status (start evaluation; evaluation outcomes with a
  requirements checklist; record compliance per deficiency; verify payment & accept / reject;
  resume after customer action), the requester's identity with verify/reject, and cancel.

## Dropping copies (2026-11-05)

The client's workflow has no number of copies, so `credential_requests.copies` is dropped. Before
the column goes, the migration sets `amount_due = fee` on requests that have not reached payment
yet (`submitted` through `awaiting_payment`), so nobody is asked to pay for copies that will not
be made. Requests at `payment_verification` or later keep the amount that was paid.

## Documentary-requirement files (2026-11-10)

- `request_documents` gains a nullable `requirement` (text, since a requirement line may be long): the requirement's text as the
  credential listed it at submission, so a later edit of the master list does not relabel a file.
  Rows have `kind = requirement` and no representative. Same private disk
  (`RequestWorkflow::proofDisk()`, folder `request-documents/{id}`), same route
  (`request-documents.show`) and policy (owner, registrar, admin) as the SPA and IDs.
- New Request holds `requirementFiles[credential type id][requirement index] = list<file>`. Keying
  by credential keeps a file for one credential's "Form 137" from landing under another credential's
  first requirement when the requester switches. Only the chosen credential's files are checked and
  sent. Rules: `array|max:5` per requirement, each `file|mimes:jpg,jpeg,png,webp,pdf|max:5120`.
- `RequestWorkflow::attachRequirementDocuments(request, actor, [requirement => files])` stores
  the files, then creates the rows in a transaction; it deletes the stored files if that fails.
  It runs inside New Request's submit transaction, which also deletes them if a later step fails.
  No ledger entry of its own: the files arrive with the *Submitted* entry.
- `<x-requirement-document-list>` lists each requirement with its files (or "No file uploaded")
  for the evaluator's *Academic Record Verification* card, the Records page's details and the
  owner's Request Details.

