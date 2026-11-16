# 04 — Submission, Evaluation, Compliance, Payment & Acceptance · Requirements

Source: *Proposed Workflow*, sections III (request, evaluation, incomplete requirements, the
portal notice) and V (payment verification and official acceptance). Core principle: the date a
request is **submitted** differs from the date it is **accepted for processing**; the processing
period starts only at acceptance.

## User stories

- **US-04-1** As a requester, I want to see a credential's requirements, fee, processing period,
  signatories and special conditions before I submit, so that I know what to prepare.
- **US-04-2** As a requester, I want a tracking number and confirmation that my request was
  received for evaluation, and to be told exactly what is required if my requirements are incomplete.
- **US-04-3** As the Registrar's evaluator, I want to record the outcome of my evaluation
  (complete, incomplete, needs clarification, not eligible), so that each request follows the
  right path and nothing proceeds to payment with requirements still to submit.
- **US-04-4** As the registrar, I want to verify a payment against the official record before
  accepting the request, so that an uploaded screenshot alone never counts as paid.

## Acceptance criteria

### Submission
- **REQ-04-01** THE SYSTEM SHALL let a requester (giving their details and ID on the same form,
  REQ-15-03) submit a request for an active credential type that has a route, with the purpose (chosen from the registrar's list, spec 13), and — when the credential type requires it — the complete name of the receiving school.
- **REQ-04-02** BEFORE a request is submitted THE SYSTEM SHALL let the requester choose the
  credential from a dropdown and, once one is chosen, show its description, fee, processing
  period, offices on its route and special conditions; THE SYSTEM SHALL also show the *Incomplete
  requirements* notice of the client's workflow. *(Documentary requirements no longer shown since
  2026-11-15, REQ-04-23.)*
- ~~REQ-04-18, REQ-04-19~~ *Superseded by REQ-04-22 (user's decision, 2026-11-15):* the optional
  upload of copies of the documentary requirements on New Request, and their listing for the
  evaluator, Records and the owner, are removed. The client's workflow never asks the requester to
  send these documents online: the evaluator checks them against the university's records (III-B),
  and the originals are submitted in person (III-C).
- ~~REQ-04-22~~ *Superseded by REQ-04-23 (user's decision, 2026-11-15, the same day):* New
  Request's section "2. Documentary Requirements", its summary row, and the requirement row on the
  evaluator's *Credential Request Verification*, the Records page and the owner's request page are
  removed.
- **REQ-04-23** THE SYSTEM SHALL NOT list a credential's documentary requirements on New Request,
  Edit Request, their summary, the evaluator's *Credential Request Verification*, the Records page
  or the requester's request page, and SHALL NOT take a file for them; no file of a documentary
  requirement is stored. A credential's list (credential master list, spec 03) SHALL be used only
  as the checkboxes of Require Office Visit (REQ-20-04): when the evaluator finds something
  required, the requester is asked to visit the office. *(User's decision, 2026-11-15. This departs
  on purpose from Proposed Workflow III-A step 6, where the customer sees the applicable documentary
  requirements before submitting: the user chose to show only what the evaluator requires at the
  office visit.)*
- **REQ-04-03** WHEN a request is submitted THE SYSTEM SHALL give it the status `submitted`, a
  unique tracking number `cr<2-digit year>-<id>` in lowercase, the id padded with zeros to at
  least 5 digits (`cr26-00042`) and growing past 99999 without a cap (`cr26-100000`); requests
  numbered earlier keep their number *(user's request, 2026-11-11)*, a snapshot of the requester's name, contact
  number, academic profile and ID (REQ-15-06) and of the fee (the amount due is the fee: one request is one document), and confirm that it was received for evaluation.
- **REQ-04-04** THE SYSTEM SHALL list a requester's own requests (newest first) and SHALL NOT show
  other requesters' requests (404).

### Evaluation
- **REQ-04-05** THE SYSTEM SHALL let the registrar start the evaluation of a submitted request,
  recording when and by whom (status `under_evaluation`).
- ~~REQ-04-06, REQ-04-07~~ *Superseded by REQ-16-10..14 (2026-11-06):* the evaluator confirms or
  rejects each part of a checklist, then approves, returns to the requester or declines; approving
  verifies the ID.
- **REQ-04-08** WHEN the requirements are complete THE SYSTEM SHALL move a paid request to
  `awaiting_payment` with payment instructions, and accept a free request directly (REQ-04-14).

### Compliance and clarification
- ~~REQ-04-09, REQ-04-10, REQ-04-11~~ *Superseded by REQ-16-13, 15 and 16:* a returned request is
  pending compliance with its rejected parts and remarks; the requester resubmits, or the registrar
  reopens it after an in-person compliance. There are no separate deficiencies or clarification.

### Payment
- **REQ-04-12** WHILE a request is `awaiting_payment` THE SYSTEM SHALL show the amount due and a
  "Pay online" link to the UPHSL payment portal, and let the owner upload a proof of payment (JPG,
  PNG, WebP or PDF, max 5 MB, stored privately) with the payment reference, amount and date paid
  (status `payment_verification`). The proof field SHALL be the valid ID's picker (REQ-15-04): the
  chosen file's name is a link that opens it in the file viewer. *(User's request, 2026-11-13.)*
- **REQ-04-13** THE SYSTEM SHALL let the registrar reject a proof with remarks (back to
  `awaiting_payment`) or verify it against the official record; an uploaded proof alone SHALL NOT
  mark a request as paid. *(Since 2026-11-16 no official receipt number is recorded; it is not in
  the BRD, REQ-16-17.)*
- **REQ-04-20** WHEN the requester uploads a new proof of payment after one was returned (REQ-04-13,
  the Proposed Workflow's "request clarification or valid proof → customer uploads proof again")
  THE SYSTEM SHALL offer an optional note to the Office of the Registrar (at most 500 characters),
  record it with the upload in the ledger, and show it to the registrar on Payment Validation, in
  the "Payment to verify" notification and email, and in both histories. The field SHALL NOT show
  before a proof was returned. The statuses are unchanged. *(User's request, 2026-11-13.)*
- **REQ-04-21** THE requester's payment section SHALL NOT list the uploaded proofs inline. WHEN a
  proof was uploaded THE notice at the top of the section ("Your last proof of payment was not
  accepted" or "Your payment is being verified") SHALL have a **Payment History** button on its
  right that opens a dialog listing every proof, newest first: the file name as a link to the file
  viewer, when it was uploaded, the reference and amount, the registrar's remarks and its status.
  *(User's request, 2026-11-13: the section showed too much at once.)*

### Acceptance
- **REQ-04-14** WHEN the registrar verifies the payment (or completes the evaluation of a free
  request) THE SYSTEM SHALL accept the request for processing: record the acceptance date and the
  staff member, set the due date from the working calendar (spec 10), copy the route and start
  it (spec 05), and set the status `accepted_for_processing`.
- ~~REQ-04-15~~ *Superseded by REQ-16-21:* the registrar does not change what the requester
  entered.

### Cancellation and ledger
- **REQ-04-16** THE SYSTEM SHALL let the owner cancel their request before acceptance (reason
  optional). *(The registrar's cancellation was removed by REQ-16-21.)*
- **REQ-04-17** THE SYSTEM SHALL record every action in the append-only ledger and SHALL refuse
  actions that are not allowed in the request's current status.
