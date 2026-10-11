# 05 — Department Signing, Hold, Skip & Release · Requirements

## User stories

- **US-05-1** As department staff, I want to see the requests waiting for my office's
  signature, so that I can sign them in order.
- **US-05-2** As department staff, I want to put a request on hold with a reason (e.g. unpaid
  library fine), so that the student knows what to settle.
- **US-05-3** As the registrar, I want to skip a step, release finished requests and cancel
  requests, so that I stay in control of the flow.

## Acceptance criteria

### Department queue
- **REQ-05-01** THE SYSTEM SHALL show department staff the in-progress requests whose current
  step belongs to their department, split into "For signature" and "On hold", oldest first,
  with reference number, credential, student, college and how long it has been waiting.
- **REQ-05-02** THE SYSTEM SHALL show department staff a history of steps their department has
  signed or that were skipped, newest first.
- **REQ-05-03** THE SYSTEM SHALL let department staff open a request that has a step for their
  department (details and signing route, no payment receipts); other requests SHALL respond 404.

### Signing
- **REQ-05-04** WHEN staff sign the current step of their department THE SYSTEM SHALL mark it
  `signed` with the signer, time and optional remarks, and make the next step `pending`.
- **REQ-05-05** WHEN the last step is signed THE SYSTEM SHALL set the request to
  `ready_for_release`.
- **REQ-05-06** WHEN staff put the current step on hold THE SYSTEM SHALL require remarks and
  set the step to `on_hold`; staff MAY later lift the hold (back to `pending`) or sign directly.
- **REQ-05-07** IF a user acts on a step that is not the current step, not their department's,
  or whose request is not `in_progress` THEN THE SYSTEM SHALL refuse it without changes.
- **REQ-05-08** THE SYSTEM SHALL let registrar accounts act on steps of the Office of the
  Registrar (code `REG`) in addition to that office's department staff; registrars see these in
  an "Office of the Registrar" queue on their dashboard.

### Registrar control
- **REQ-05-09** THE SYSTEM SHALL let the registrar skip the current step of any in-progress
  request with a required reason; the flow then continues as if it were signed.
- **REQ-05-10** WHILE a request is `ready_for_release` THE SYSTEM SHALL let the registrar mark it
  `released` (claimed by the student).
- **REQ-05-11** THE SYSTEM SHALL let the registrar cancel any open request, including one in
  progress, with a required reason; it then disappears from department queues.

### Ledger & concurrency
- **REQ-05-12** WHEN any action in this spec happens THE SYSTEM SHALL append a ledger row naming
  the step's department, the actor and any remarks.
- **REQ-05-13** IF two people act on the same step at the same time THEN only the first action
  SHALL succeed; the second SHALL be refused because the step is no longer current.
