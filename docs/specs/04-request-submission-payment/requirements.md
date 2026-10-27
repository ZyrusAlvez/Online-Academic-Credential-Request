# 04 — Submission, Evaluation, Compliance, Payment & Acceptance · Requirements

Source: *Proposed Workflow*, sections III (request, evaluation, incomplete requirements, the
portal notice) and V (payment verification and official acceptance). Core principle: the date a
request is **submitted** differs from the date it is **accepted for processing**; the processing
period starts only at acceptance.

## User stories

- **US-04-1** As a requester, I want to see a credential's requirements, fee, processing period,
  signatories and special conditions before I submit, so that I know what to prepare.
- **US-04-2** As a requester, I want a tracking number and confirmation that my request was
  received for evaluation, and to be told exactly what is missing if my requirements are incomplete.
- **US-04-3** As the Registrar's evaluator, I want to record the outcome of my evaluation
  (complete, incomplete, needs clarification, not eligible), so that each request follows the
  right path and nothing proceeds to payment with missing requirements.
- **US-04-4** As the registrar, I want to verify a payment against the official record before
  accepting the request, so that an uploaded screenshot alone never counts as paid.

## Acceptance criteria

### Submission
- **REQ-04-01** THE SYSTEM SHALL let a requester whose profile is complete (spec 01, REQ-01-18)
  submit a request for an active credential type that has a route, with the number of copies
  (1–10), the purpose, and — when the credential type requires it — the complete name of the receiving school.
- **REQ-04-02** BEFORE a request is submitted THE SYSTEM SHALL let the requester choose the
  credential from a dropdown and, once one is chosen, show its description, documentary
  requirements, fee per copy and amount due, processing period, offices on its route and special
  conditions; THE SYSTEM SHALL also show the *Incomplete requirements* notice of the client's
  workflow.
- **REQ-04-03** WHEN a request is submitted THE SYSTEM SHALL give it the status `submitted`, a
  unique tracking number `CR-<year>-<6-digit id>`, a snapshot of the requester's academic profile
  and of the fee (amount due = fee × copies), and confirm that it was received for evaluation.
- **REQ-04-04** THE SYSTEM SHALL list a requester's own requests (newest first) and SHALL NOT show
  other requesters' requests (404).

### Evaluation
- **REQ-04-05** THE SYSTEM SHALL let the registrar start the evaluation of a submitted request,
  recording when and by whom (status `under_evaluation`).
- **REQ-04-06** WHILE a request is under evaluation THE SYSTEM SHALL show the evaluator the
  requester's profile, identity status and ID, and the credential's requirements, and let them
  record one of four outcomes: requirements complete; requirements incomplete (one or more listed
  deficiencies, status `pending_compliance`); clarification needed (a message to the requester,
  status `returned_for_customer_action`); not eligible (reason required, status `declined`).
- **REQ-04-07** IF the requester's identity is not yet verified THEN THE SYSTEM SHALL NOT let the
  evaluator mark the requirements complete.
- **REQ-04-08** WHEN the requirements are complete THE SYSTEM SHALL move a paid request to
  `awaiting_payment` with payment instructions, and accept a free request directly (REQ-04-14).

### Compliance and clarification
- **REQ-04-09** WHILE a request is `pending_compliance` THE SYSTEM SHALL show the requester each
  deficiency and the notice that originals must be submitted in person or through an authorized
  representative with an SPA, and that the processing period has not started.
- **REQ-04-10** THE SYSTEM SHALL let the registrar record the compliance of each deficiency (what
  was received or the clearance status, with the date and the verifying staff member), and SHALL
  NOT let a request with unresolved deficiencies proceed to payment.
- **REQ-04-11** WHILE a request is `returned_for_customer_action` THE SYSTEM SHALL show the
  requester the registrar's message and let them reply; WHEN they reply, or the registrar records
  that they complied, THE SYSTEM SHALL return the request to its previous stage.

### Payment
- **REQ-04-12** WHILE a request is `awaiting_payment` THE SYSTEM SHALL show the amount due and a
  "Pay online" link to the UPHSL payment portal, and let the owner upload a proof of payment (JPG,
  PNG, WebP or PDF, max 5 MB, stored privately) with the payment reference, amount and date paid
  (status `payment_verification`).
- **REQ-04-13** THE SYSTEM SHALL let the registrar reject a proof with remarks (back to
  `awaiting_payment`) or verify it against the official record, recording the official receipt
  number; an uploaded proof alone SHALL NOT mark a request as paid.

### Acceptance
- **REQ-04-14** WHEN the registrar verifies the payment (or completes the evaluation of a free
  request) THE SYSTEM SHALL accept the request for processing: record the acceptance date and the
  staff member, set the due date from the working calendar (spec 10), copy the route and start
  it (spec 05), and set the status `accepted_for_processing`.
- **REQ-04-15** THE SYSTEM SHALL let the registrar correct the requester's college before
  acceptance.

### Cancellation and ledger
- **REQ-04-16** THE SYSTEM SHALL let the owner cancel their request before acceptance (reason
  optional), and the registrar cancel any open request (reason required).
- **REQ-04-17** THE SYSTEM SHALL record every action in the append-only ledger and SHALL refuse
  actions that are not allowed in the request's current status.
