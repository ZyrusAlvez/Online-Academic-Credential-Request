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
- **REQ-04-01** THE SYSTEM SHALL let a requester (giving their details and ID on the same form,
  REQ-15-03) submit a request for an active credential type that has a route, with the purpose (chosen from the registrar's list, spec 13), and — when the credential type requires it — the complete name of the receiving school.
- **REQ-04-02** BEFORE a request is submitted THE SYSTEM SHALL let the requester choose the
  credential from a dropdown and, once one is chosen, show its description, documentary
  requirements, fee, processing period, offices on its route and special
  conditions; THE SYSTEM SHALL also show the *Incomplete requirements* notice of the client's
  workflow.
- **REQ-04-18** WHERE the chosen credential lists documentary requirements THE SYSTEM SHALL offer,
  on New Request, one file field per requirement, labelled with the requirement, taking up to 5
  files of JPG, PNG, WebP or PDF, 5 MB each. Every field is optional and SHALL NOT be marked
  "Optional"; each file is checked as soon as it is chosen. Each field SHALL list every file
  chosen for it by name and size, each with its own Remove, and count them ("2 of 5 files");
  choosing files again adds to them, so the button then reads "Add more files" and is gone at
  5. *(User's report, 2026-11-10: the browser's own picker showed only the last file's name.)*
  The count SHALL stay at the right of the requirement's name, however long the name, and the
  button at the right of the field; while files upload, the field SHALL show a progress bar with
  the percentage in place of the button. *(User's request, 2026-11-10.)*
  Files chosen for another credential SHALL NOT be sent. *(User's decision, 2026-11-10: copies help the evaluator; the originals are
  still checked at the Registrar's Office, REQ-16-15.)*
- **REQ-04-19** THE SYSTEM SHALL store the requirement files privately with the request, each
  under its requirement, and SHALL let only the owner, registrars and admins open them; the
  evaluator sees them under *Academic Record Verification*, and they appear on the Records page
  and the owner's request page.
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
  (status `payment_verification`).
- **REQ-04-13** THE SYSTEM SHALL let the registrar reject a proof with remarks (back to
  `awaiting_payment`) or verify it against the official record, recording the official receipt
  number; an uploaded proof alone SHALL NOT mark a request as paid.

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
