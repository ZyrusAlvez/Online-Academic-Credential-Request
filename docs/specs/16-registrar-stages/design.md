# 16 — Registrar Stages · Design

## Data

`request_checks`: the checklist's current state per request, not the ledger.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `credential_request_id` | FK → `credential_requests`, restrict | |
| `item` | string(30) | `App\Enums\CheckItem` |
| `result` | string(20) | `App\Enums\CheckResult`: `confirmed`, `rejected` |
| `checked_by` | FK → `users`, restrict | |
| `checked_at` | datetime | |

There is a unique key on (`credential_request_id`, `item`). An item belongs to one stage
(`App\Enums\CheckStage`: `evaluation`, `payment`), so the stage is not stored. A
result is updated when the evaluator changes their mind. Clearing the rejected parts on resubmit or
reopen deletes their rows. The decision's ledger entry keeps the history (REQ-16-22).

`releases.id_presented` becomes nullable: release now records who claimed the credential,
not the ID they showed (REQ-16-20).

`CheckItem` cases, by stage:

| Stage | Items |
|---|---|
| evaluation | `credential_request`, `identity`, `academic_record`, `representative` (only when `requester_mode` is representative) |
| payment | `payment_proof`, `payment_amount` |

`CheckItem::for(CheckStage, CredentialRequest)` lists the items that apply to a request.

The final quality check has no checklist (REQ-16-19): its page shows the credential's details and
route read-only. The migration `2026_11_13_000001_remove_final_check_items` deletes the
`credential_document` and `signatures` rows saved before; each final check stays in the ledger
(`StepCompleted` with the registrar, time and remarks).

## Workflow (`App\Services\RequestWorkflow`)

| Method | From → to | Notes |
|---|---|---|
| `startEvaluation` | submitted → under_evaluation | sets `evaluated_by` (the lock); keeps the first `evaluation_started_at` |
| `recordCheck(request, registrar, item, result)` | — | only in the item's stage; evaluation items only by the evaluator |
| `approveEvaluation(request, registrar, ?remarks)` | under_evaluation → awaiting_payment / accepted | every item confirmed; marks the ID and pending representatives verified; event `RequirementsCompleted` |
| `returnToRequester(request, registrar, remarks)` | under_evaluation → pending_compliance | needs a rejected item; marks the ID or representatives rejected for rejected parts; event `ComplianceRequested` |
| `decline(request, registrar, reason)` | under_evaluation, payment_verification, for_final_approval → declined | at the final check it closes the open visit and step |
| `resubmit(request, owner, ?note)` | pending_compliance → submitted | clears rejected items and the lock; event `Resubmitted`, registrars notified |
| `reopenEvaluation(request, registrar)` | pending_compliance → under_evaluation | clears rejected items; the registrar becomes the evaluator |
| `verifyPaymentAndAccept` | payment_verification → accepted_for_processing | also needs both payment items confirmed |
| `rejectPayment` | payment_verification → awaiting_payment | unchanged; clears the payment items so the next proof is checked anew |
| `approveFinalCheck(request, registrar, ?remarks)` | for_final_approval → ready_for_release | completes the final-check visit (`completeVisit`) |
| `release(request, registrar, recipientType, ?representativeId)` | ready_for_release → released | the name comes from the owner or the representative |

`forward()` moves `accepted_for_processing` to `in_progress` when it sends the document on from an
office (REQ-16-18). `acceptReceipt()` only sets `for_final_approval` for the final check, or
`in_progress` again when a corrected document is received back.

Removed: `changeCollege`, `requestCompliance`, `resolveDeficiency`, `requestCustomerAction`,
`customerResponded`, `bypassStep`, `reviewIdentity`. `ReturnedForCustomerAction`, the deficiency
table, pauses and their event types stay, so older rows and ledger entries still read correctly.
Nothing creates new ones.

## Authorization (`CredentialRequestPolicy`)

- `evaluate`: a registrar, and the request is not under evaluation by someone else. The working
  page and every evaluation action check it.
- `cancel`: the owner only, before acceptance.
- `resubmit`: the owner, while pending compliance.

## Screens

| Route | Component | Notes |
|---|---|---|
| `registrar/evaluation` | `Registrar\Queue` (stage `evaluation`) | `view=waiting` shows pending compliance |
| `registrar/payments` | `Registrar\Queue` (`payment`) | |
| `registrar/in-progress` | `Registrar\Queue` (`in_progress`) | office filter; links to Records |
| `registrar/final-check` | `Registrar\Queue` (`final_check`) | |
| `registrar/release` | `Registrar\Queue` (`release`) | |
| `registrar/records` | `Registrar\Records\Index` | `RegistrarRequestFilter`, no export |
| `registrar/records/{request}` | `Registrar\Records\Show` | read-only |
| `registrar/requests/{request}` | `Registrar\Requests\Show` | the working page for the current stage |

`App\Queries\StageQueue` builds each queue's query. Every queue but Release has a `search`
property (`?search=` in the URL, debounced) that `StageQueue::for()` applies with
`RegistrarRequestFilter::matching()`, the same match as Records, so the two searches never drift
(REQ-16-25). The "Waiting for the requester (n)" count stays the tab's full count. On the queues
with that tab (Initial Evaluation, Payment Validation) the search box sits on the tabs' line,
right-aligned; it wraps below them on narrow screens.

The working page shows one stage panel. It uses `x-check-card`: a heading, the part's data, and
on the right Confirm / Reject with the current result. A part's data is a `<dl>` of
`x-summary-row`s, the same row as the requester's summary (REQ-16-24). Under the cards are
Remarks and the decision buttons. Each stage's panel:

- Submitted: Start evaluation.
- Under evaluation by someone else: the notice only.
- Pending compliance: the rejected parts and Reopen evaluation.
- At the Registrar's own preparation step or a correction: the office buttons
  (`partials.visit-actions`), with no delay reason.
- Anything else: read-only.

The ledger is always shown at the bottom.

The requester's page, while pending compliance, shows the rejected parts with the remarks, the
existing new-ID form, the representative form, a note and Resubmit.
