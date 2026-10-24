# 05 — Office Routing: Receipt, Release, Returns & Final Check · Requirements

Source: *Proposed Workflow*, section VI (document processing, routing and signatory monitoring),
the TOR routing example, and section X (return and reprocessing control, deadline rules).

## User stories

- **US-05-1** As office staff, I want to see the documents sent to my office and confirm their
  actual receipt, so that the system knows when my office really has them.
- **US-05-2** As office staff, I want to approve and release a document to the next office, or
  return it for correction with a reason, so that every hand-off is recorded.
- **US-05-3** As the registrar, I want each office visit kept separately, the original due date
  preserved and approved pauses recorded apart, so that delays are measured fairly.

## Acceptance criteria

### Route
- **REQ-05-01** WHEN a request is accepted THE SYSTEM SHALL copy the credential type's route
  (the Dean step resolved to the requester's college), append the Registrar's final quality check
  as the last step, and forward the document to the first office.
- **REQ-05-02** THE SYSTEM SHALL show office staff, in their queue, the documents forwarded to
  their office and not yet received, the documents in their office, and their office's history.

### Office actions
- **REQ-05-03** THE SYSTEM SHALL let staff of the office a document was forwarded to accept its
  receipt, recording the office, the staff member and the time; forwarding SHALL NOT count as
  receipt. The first receipt puts the request `in_progress`; receipt for the final check puts it
  `for_final_approval`.
- **REQ-05-04** THE SYSTEM SHALL let staff of the office holding a received document approve it
  (prepared, signed, stamped or checked) and release it, which records the completion time and
  forwards it to the next office on the route.
- **REQ-05-05** WHEN the Registrar completes the final quality check THE SYSTEM SHALL mark the
  request `ready_for_release`.
- **REQ-05-06** THE SYSTEM SHALL let staff of the office holding a received document return it for
  correction to an earlier office on its route (the previous office by default), with the reason
  and the corrective action required (status `returned_for_correction`), and notify that office.
- **REQ-05-07** WHEN the office a document was returned to has received and corrected it THE
  SYSTEM SHALL let it forward the document back to the office that returned it, which receives it
  again before the route continues.
- **REQ-05-08** THE SYSTEM SHALL record every office visit (forwarded, received, completed or
  returned, with times, staff and office) as a separate record and SHALL never overwrite an
  earlier visit; the time spent per visit is counted in working days (spec 10).
- **REQ-05-09** THE SYSTEM SHALL let staff of the office holding a document, and the registrar,
  record a delay reason (one of the client's delay categories, an explanation and the corrective
  action) without changing the request's status.
- **REQ-05-10** THE SYSTEM SHALL let the registrar bypass the current step (other than the final
  check) with a required reason, forwarding the document to the next office.
- **REQ-05-11** THE SYSTEM SHALL let only the staff of the office concerned act on a visit, and
  registrar accounts on the Office of the Registrar's visits; the first action wins when two people
  act at once.

### Due dates and pauses
- **REQ-05-12** WHILE a request is being processed and past its effective due date THE SYSTEM SHALL
  flag it as overdue to the registrar.
- **REQ-05-13** THE SYSTEM SHALL let the registrar return an accepted request for customer action
  with a message; this pauses the processing period. WHEN the customer responds or the registrar
  records their compliance THE SYSTEM SHALL resume it, keep the original due date and set an
  adjusted due date later by the paused working days.

### Ledger
- **REQ-05-14** THE SYSTEM SHALL record every office action in the append-only ledger.
