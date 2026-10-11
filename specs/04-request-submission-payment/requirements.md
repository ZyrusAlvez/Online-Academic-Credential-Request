# 04 — Request Submission, Payment & Registrar Review · Requirements

## User stories

- **US-04-1** As a student, I want to request a credential online, giving the details the
  Registrar needs, so that I don't have to fill in a paper form.
- **US-04-2** As a student requesting a paid credential, I want to pay on the UPHSL payment
  portal and upload my receipt, so that the Registrar can verify my payment.
- **US-04-3** As the registrar, I want to review new requests and receipts, then approve them
  so that they start moving through the offices.

## Acceptance criteria

### Submission
- **REQ-04-01** THE SYSTEM SHALL let a verified student submit a request with: credential type
  (active and with a workflow), number of copies (1–10), student number, college (active
  college), program/course, status (current student or alumnus), last school year attended
  (`YYYY-YYYY`, consecutive years) and purpose.
- **REQ-04-02** WHEN a request is submitted THE SYSTEM SHALL assign a unique reference number
  `CR-<year>-<6-digit id>` and snapshot the credential's payment requirement and fee.
- **REQ-04-03** WHEN a request for a paid credential is submitted THE SYSTEM SHALL set its
  status to `awaiting_payment`; otherwise to `registrar_review`.
- **REQ-04-04** THE SYSTEM SHALL list a student's own requests (newest first) with reference
  number, credential, status and date, and SHALL NOT show other students' requests (404).

### Payment
- **REQ-04-05** WHILE a request is `awaiting_payment` THE SYSTEM SHALL show the fee and a
  "Pay online" link to the UPHSL payment portal that opens in a new tab.
- **REQ-04-06** WHILE a request is `awaiting_payment` THE SYSTEM SHALL let its owner upload a
  proof of payment (JPG, PNG, WebP or PDF, max 5 MB) with an optional payment reference number
  and amount paid; the request then moves to `payment_review`.
- **REQ-04-07** THE SYSTEM SHALL store proofs privately; only the request owner and registrar
  accounts SHALL be able to view them.
- **REQ-04-08** WHEN the registrar rejects a payment THE SYSTEM SHALL require remarks, mark the
  proof `rejected`, and return the request to `awaiting_payment` so the student can upload again.
  Earlier proofs SHALL be kept.

### Registrar review & routing
- **REQ-04-09** THE SYSTEM SHALL list requests needing registrar review (`payment_review`,
  `registrar_review`) for the registrar, with search by reference number or student name.
- **REQ-04-10** WHILE a request is under review THE SYSTEM SHALL let the registrar correct the
  request's college.
- **REQ-04-11** WHEN the registrar approves a request in `registrar_review`, or accepts the
  payment of a request in `payment_review`, THE SYSTEM SHALL copy the credential type's current
  workflow into the request's own steps (resolving "Student's College" to the request's
  college), set step 1 to `pending`, the others to `waiting`, and the request to `in_progress`.
- **REQ-04-12** IF the credential type has no workflow steps at approval time THEN THE SYSTEM
  SHALL refuse the approval with an error.

### Cancellation by the student
- **REQ-04-13** WHILE a request is `awaiting_payment`, `payment_review` or `registrar_review`
  THE SYSTEM SHALL let its owner cancel it with an optional reason.

### Ledger
- **REQ-04-14** WHEN any transition in this spec happens THE SYSTEM SHALL append a
  `request_events` row with the actor, event type, from/to status and remarks. Ledger rows
  SHALL NOT be updatable or deletable through the application.
- **REQ-04-15** IF a transition is attempted from a status that does not allow it THEN THE
  SYSTEM SHALL refuse it without changing anything.
