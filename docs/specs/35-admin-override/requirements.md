# 35 — Admin Override · Requirements

Source: *Proposed Workflow*, module 12 ("Administration and Audit Trail"), section X ("Complete
audit trail … with timestamps and authorized user details") and section IX-D, which names "System
or technical issue: system downtime or unavailable electronic records" as a cause of delay.

Decided by the user on 2026-11-23. Every step of a request belongs to one role: an office accepts
receipt and approves; the Registrar forwards, does the final quality check and releases; the
Cashier validates online payments; one evaluator holds a request while evaluating it (REQ-16-09).
When that person can't use the system (the system was down, they are absent or locked out, their
office's computer or connection is down), the paper keeps moving but the request stops in the
system. The Admin, who until now only looks (REQ-18-05), can record such a request's **next step**
on behalf of whoever couldn't, with a reason and the date and time it really happened, so the work
moves on and the ledger matches the paper.

The override is limited to steps that record what physically happened: receipt, approval and
forwarding of the paper, the final quality check, payment validation, release, and unlocking an
evaluation. Judgment calls stay with the staff.

This changes:

- REQ-05-11 and REQ-32-04: the Admin also acts on a visit, through an override.
- REQ-16-07: the Records page has one action, the Admin's Override.
- REQ-16-09: the evaluation lock also ends when the Admin unlocks it.
- REQ-16-20, REQ-25-05: the Admin can also release, or validate an online payment, through an
  override.
- REQ-32-11: the Registrar is told of a paper waiting in Outgoing whoever recorded the office's
  step.
- REQ-06-02, REQ-06-21, REQ-14-01: the new ledger entry and the override marking.

REQ-18-05 stands: the Registrar's queues and working pages stay the Registrar's; the override is on
Records.

## User stories

- **US-35-1** As the Admin, I want to record a request's next step for the office or staff member
  who couldn't, so that the work moves on and the record matches where the paper is.
- **US-35-2** As the Admin, I want to enter when each step really happened, so that office times
  and on-time reports stay right after the system was down.
- **US-35-3** As the Registrar or an auditor, I want every override marked with who recorded it,
  when and why, so that the record can still be trusted.

## Acceptance criteria

### The Override
- **REQ-35-01** WHILE a credential has a step the Admin can record (REQ-35-02..05) THE SYSTEM SHALL
  show the Admin an **Override** button on that credential's record (`/records/{tracking no.}`,
  the credential's tab), on the "Where it is now" line. IF anyone but the Admin calls the override
  THEN THE SYSTEM SHALL respond 403. The Registrar's view of the record SHALL stay read-only.
- **REQ-35-02** THE Override SHALL offer the step the paper is waiting for, and run the same
  workflow action the office or the Registrar would, with the same effects:
  - forwarded to an office and not yet received: **Accept receipt** at that office (REQ-05-03);
  - received at an office other than the Office of the Registrar: **Approve** that office's task,
    or finish its correction (REQ-05-04, 07);
  - finished at an office, or held by the Office of the Registrar for its own task: **Forward** to
    the next office, or back to the office it returns to (REQ-32-03, 08);
  - at the final quality check: **Approve** it, which makes it ready for release (REQ-16-19).
- **REQ-35-03** WHILE the payment awaits validation THE Override SHALL offer **Validate payment**:
  for an online request it verifies the proof under review and accepts the request (REQ-25-05's
  Approve); for a walk-in request it records the official receipt number and the amount paid and
  accepts the request (REQ-26-05).
- **REQ-35-04** WHILE a credential is ready for release THE Override SHALL offer **Mark released**,
  to the owner, a verified representative or a representative checked at the counter, as
  REQ-16-20 and 28.
- **REQ-35-05** WHILE a request is under evaluation THE Override SHALL offer **Unlock**: the
  request goes back to `submitted` in Initial Evaluation, where any registrar can start its
  evaluation and becomes its evaluator (REQ-16-09). The checklist results recorded so far SHALL be
  kept, and stay changeable.
- **REQ-35-06** THE Override SHALL NOT offer an evaluation decision, a return to the requester, a
  return for correction, a decline or skipping an office (REQ-16-21), and SHALL offer nothing for a
  request waiting for the requester (clarification, compliance, payment) or closed (released,
  declined, cancelled).
- **REQ-35-07** THE Override SHALL require a reason, up to 500 characters.
- **REQ-35-08** THE Override SHALL ask when the step happened, set to the current time when it is
  opened. IF that time is in the future, or before the minute of the request's latest history
  entry, THEN THE SYSTEM SHALL refuse it, changing nothing. A time in the same minute as that
  entry SHALL take the entry's time.
- **REQ-35-09** THE step SHALL take that time wherever it would have taken the current time: its
  history entries, the office visit's received, done and completed times and its working days, the
  office's deadline, the accepted time and due date, the ready-for-release time and processing
  days, the payment's review time, and the release time.
- **REQ-35-10** EVERY history entry an override writes SHALL record the Admin as the one who did
  it, the reason and the time it was recorded. Staff SHALL see on the entry that it is an Admin
  override, when it was recorded and why. THE requester SHALL NOT see the reason or that it was an
  override (REQ-06-02).
- **REQ-35-11** THE activity log SHALL log each entry an override writes at the time it was
  recorded, by the Admin, with the reason and when the step happened.
- **REQ-35-12** AN override SHALL notify as the step it records does (spec 06): the requester when
  the request is accepted, ready for release or released; an office when the document is forwarded
  to it; the request's registrar when an office's finished paper waits in Outgoing (REQ-32-11).
  WHEN the Admin unlocks an evaluation THE SYSTEM SHALL notify every active registrar, as for a new
  request (REQ-06-21), and SHALL NOT tell the requester.
- **REQ-35-13** IF the request has moved on since the Override was opened THEN THE SYSTEM SHALL
  refuse the step, changing nothing, and say so; the Override then offers the request's new next
  step. The first action wins (REQ-05-11).
