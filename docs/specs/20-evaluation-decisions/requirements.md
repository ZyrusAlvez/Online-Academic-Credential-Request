# 20 — Initial Evaluation Decisions · Requirements

Source: *Proposed Workflow*:

- **Section III-B**, the evaluation results: requirements complete, requirements incomplete
  (physical compliance at the Registrar's Office), clarification needed, and not eligible.
- **Section III-C**, the procedure when requirements are incomplete: the requester submits the
  originals and completes clearance in person or through a representative, and the Registrar
  records the documents received, the clearance, the date and who verified them. "Requests with
  incomplete requirements must remain under Pending Compliance."
- **Section V**, the note after the status table: a request returned to the requester and a
  document returned between offices are different events, with separate statuses and reports.
- **Section VIII-A**, the customer dashboard statuses, which include "Returned for Customer
  Action".

Decided by the user on 2026-11-10. Spec 16 had one return, and the requester could resubmit it
online even when original documents were missing. Initial Evaluation now ends with one of four
decisions:

| Button | Status | What happens next |
|---|---|---|
| **Approve** | `awaiting_payment` (a free request is accepted) | Payment instructions, as before |
| **Return for Clarification** | `returned_for_clarification` | The requester edits the request and resubmits it online |
| **Require Office Visit** | `pending_compliance` | The requester brings the missing originals or completes clearance at the office; the Registrar records it with **Confirm Compliance** |
| **Decline** | `declined` | The request ends |

This replaces:

- REQ-16-11's buttons, REQ-16-13, REQ-16-15 and REQ-16-16.
- REQ-16-02's "Waiting for the requester" view, REQ-16-23's wording for a returned request.
- REQ-15-11: a rejected ID is replaced on Edit Request.

## User stories

- **US-20-1** As an evaluator, I want to choose between asking the requester to correct the request
  online and asking them to come to the office, so that missing originals are never "fixed" with an
  upload.
- **US-20-2** As a requester whose request was returned for clarification, I want to edit what was
  wrong and resubmit it, so that it is evaluated again without a new request.
- **US-20-3** As a requester asked to visit the office, I want to see exactly what to bring, so that
  one visit is enough.
- **US-20-4** As the registrar, I want to record each missing document as it is received and then
  confirm compliance, so that the request goes on to payment with a record of who verified what.

## Acceptance criteria

### Statuses
- **REQ-20-01** THE SYSTEM SHALL have the request status `returned_for_clarification`, labelled
  "Returned for clarification" (the Proposed Workflow's "Returned for Customer Action"), listed
  after `under_evaluation` in `request_statuses` (spec 19). `pending_compliance` SHALL mean the
  requester must bring originals or complete clearance at the Office of the Registrar.
  `returned_for_customer_action` stays unused, for older requests.

### The decision
- **REQ-20-02** BELOW the evaluation checklist THE SYSTEM SHALL show Remarks and the buttons
  Decline, Require Office Visit, Return for Clarification and Approve, in that order, Approve on
  the right. Remarks SHALL be optional to approve and required for the other three.
- **REQ-20-03** WHEN the evaluator chooses Return for Clarification THE SYSTEM SHALL require at least
  one rejected part and remarks. It SHALL then set the status `returned_for_clarification` and
  record the rejected parts and the remarks. If the identity part was rejected, the request's ID
  SHALL be marked rejected. If the representative part was rejected, its representatives SHALL be
  marked not accepted. The requester SHALL be notified, and the evaluation lock ends.
- **REQ-20-04** WHEN the evaluator chooses Require Office Visit THE SYSTEM SHALL ask what is
  missing: each documentary requirement of the credential as a checkbox, plus an optional "Other"
  item. It SHALL require at least one missing item and remarks. It SHALL then record each missing
  item with the evaluator, set the status `pending_compliance`, write the missing items and the
  remarks to the ledger and notify the requester.

### Returned for clarification
- **REQ-20-05** WHILE a request is returned for clarification THE SYSTEM SHALL show its requester
  the rejected parts, the remarks and an **Edit Request** button. Nobody else SHALL be able to edit
  it, and it SHALL NOT be editable in any other status.
- **REQ-20-06** THE Edit Request page SHALL be the New Request form filled in with the request's
  credential, purpose and receiving school, and the requester's saved details. The requester SHALL
  be able to change the credential, purpose, receiving school, their name, contact number and
  academic information, and add copies of the requirements. WHEN the request's ID was rejected THE
  SYSTEM SHALL require another valid ID; the reason is shown on the request page only (REQ-20-05),
  not repeated on Edit Request *(user's request, 2026-11-11)*. A summary SHALL follow, with an
  optional note to the Office of the Registrar, the privacy consent and the button
  **Resubmit Request**.
- **REQ-20-07** WHEN the requester resubmits THE SYSTEM SHALL, all or nothing: save their details to
  their profile; update the request's credential, purpose, receiving school, fee, amount due and
  copy of their details; attach a new ID (it becomes their saved ID and awaits verification) and
  any new requirement copies; set the status `submitted`, so the request is back in the Initial
  Evaluation queue; write the note to the ledger and notify the registrars. Rejected parts SHALL be
  cleared, and so SHALL each part whose data changed: Credential request (credential, purpose,
  receiving school or new requirement copies), User identity (name, contact number or ID) and
  Academic record (academic information). Other confirmed parts SHALL stay confirmed.

### Office visit
- **REQ-20-08** WHILE a request is pending compliance THE SYSTEM SHALL show its requester "Visit the
  Office of the Registrar", the missing items with whether each was received, the remarks and the
  notice that originals are submitted in person or through an authorized representative with an
  SPA and the valid IDs of both. THE SYSTEM SHALL NOT let them edit or resubmit the request; they
  can still add a representative or cancel.
- **REQ-20-09** WHILE a request is pending compliance THE SYSTEM SHALL show the registrar its missing
  items, each with **Mark Received**, which records the date and the registrar. **Confirm
  Compliance** SHALL require every item received. It SHALL then confirm the evaluation's parts, mark
  the request's ID and its representatives verified, and move a paid request to `awaiting_payment`
  with payment instructions, or accept a free request directly. The registrar SHALL also be able to
  decline the request, with remarks.

### Queues, wording and reports
- **REQ-20-10** THE Initial Evaluation queue's "Waiting for the requester" view SHALL list the
  requests returned for clarification and those pending compliance, each with its status. The
  Records filter "Waiting for the requester" SHALL include both.
- **REQ-20-11** WHERE the system says where a request is, it SHALL say for a request returned for
  clarification "Waiting for you to correct your request" (staff: "Waiting for the requester to
  correct the request"), and for one pending compliance "Waiting for you at the Office of the
  Registrar" (staff: "Waiting for the requester at the Office of the Registrar").
- **REQ-20-12** THE Customer Compliance report SHALL list requests returned for clarification and
  those pending compliance as separate statuses: the first waiting for the evaluator's remarks, the
  second for its missing items. THE Evaluator Performance report SHALL count, per evaluator, the
  requests returned for clarification apart from those sent for compliance.
- **REQ-20-13** THE SYSTEM SHALL NOT offer the registrar's Reopen evaluation, nor let a requester
  resubmit a request pending compliance. A requester SHALL be able to cancel a request in either
  waiting status.
