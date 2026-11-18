# 32 — The Registrar Carries the Papers · Requirements

Source: *Proposed Workflow*, section VI-A (the Registrar "initiates routing"; the receiving office
accepts receipt) and VI-B (Approve / Release to Next Office, Forward Document, and the control
requirement that forwarding is not receipt).

Decided by the user on 2026-11-18. An office does not send its own papers on: the Office of the
Registrar collects a finished paper from the office and brings it to the next one. An office that
approves a document, or returns it for correction, keeps it in its **Outgoing** list (formerly
"To Forward") until the Registrar picks it up. The Registrar forwards it from its own **Outgoing**
list, one click per hand-off with no separate "collected" step, and the next office still accepts
its receipt. Returns go through the Registrar too.

This replaces:

- REQ-17-07's and REQ-17-16's "each office forwards its own papers".
- REQ-17-12's Forward button on the registrar's In Progress list (it moves to Outgoing).
- REQ-24-10's forwarding by department staff, and the "To Forward" name in REQ-24-01, 02, 06, 07.
- REQ-05-06's sending the returned document back at once.

## User stories

- **US-32-1** As the registrar, I want one list of every paper an office has finished, with where
  each goes next, and to forward them from there, so that I know what to collect and where to
  bring it.
- **US-32-2** As office staff, I want to see the papers I finished that are waiting for the
  Registrar, so that I know what is still on my desk without having to send it myself.

## Acceptance criteria

### Outgoing
- **REQ-32-01** WHEN an office approves its task THE SYSTEM SHALL keep the document in that office
  (REQ-17-06) and list it in that office's Outgoing list and in the registrar's Outgoing list.
- **REQ-32-02** THE registrar's sidebar SHALL show "Outgoing" at `/registrar/outgoing`, after In
  Progress. It SHALL list the requests being processed whose document an office finished and still
  holds, and the documents the Office of the Registrar holds for its own route task (REQ-17-12),
  oldest due date first, with the office holding it, since when, the next office (or "Return to
  <office>") and the due date. It SHALL have the search and the office filter of In Progress, and
  each row SHALL open the request's record.
- **REQ-32-03** EACH Outgoing row SHALL have a "Forward to <office>" button that forwards that
  document after a confirmation, and checkboxes with a bulk "Forward" button that forwards every
  selected document still forwardable, saying how many were forwarded and how many were not.
  Forwarding SHALL record the registrar as the one who forwarded it, open a visit at the next
  office awaiting its receipt (REQ-17-08) and notify that office (REQ-06-07).
- **REQ-32-04** ONLY registrars SHALL forward, and only from Outgoing. A forward by department
  staff, or from any other page, SHALL be refused (403).
- **REQ-32-05** THE department sidebar SHALL show "Outgoing" at `/department/outgoing` in place of
  "To Forward". It SHALL list the documents the office finished and still holds, with Next
  ("<next office>" or "Return to <office>") and no checkboxes or buttons. The former
  `/department/to-forward` addresses SHALL redirect to it.
- **REQ-32-06** THE registrar's In Progress list SHALL leave out the requests in Outgoing and SHALL
  be read-only: no Forward or Accept receipt button in any row (REQ-17-13). *(Changed
  2026-11-18, user's decision: the Accept receipt kept for papers forwarded to the Office of the
  Registrar before REQ-32-09 is removed, here and on the final quality check; staging is reset.)*

### Returns
- **REQ-32-07** WHEN an office returns a document for correction THE SYSTEM SHALL record the
  reason, the corrective action and the time, set the request `returned_for_correction`, and keep
  the document in that office's Outgoing list as "Return to <office>". It SHALL NOT complete the
  office's route step.
- **REQ-32-08** WHEN the registrar forwards a returned document THE SYSTEM SHALL close the
  returning office's visit as returned, open a correction visit at the office it was returned
  to, awaiting its receipt, and notify that office (REQ-06-07). The corrected document then goes
  back the same way: done, Outgoing, forwarded by the registrar.

### The Registrar's own receipt
- **REQ-32-09** WHEN the registrar forwards a document to the Office of the Registrar (its own
  route task, the final quality check, or a document returned to it for correction) THE SYSTEM
  SHALL record it as received there at once, by that registrar, with no Accept receipt step.
  Every other office still accepts its receipt (Proposed Workflow VI-B control requirement).
  The registrar's In Progress list SHALL describe a document forwarded to another office and not
  yet received as "Collected <time> for <office>, not yet received". *(User's decision,
  2026-11-18: the Registrar carries the paper, so it is already in hand when it reaches its own
  office.)*
