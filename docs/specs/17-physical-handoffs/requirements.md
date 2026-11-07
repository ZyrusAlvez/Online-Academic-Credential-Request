# 17 — Physical Hand-offs and the Stage Page · Requirements

Source: *Proposed Workflow*, section VI-A (the routing cycle), VI-B (the essential office buttons:
Accept Receipt, Approve / Release to Next Office, Forward Document, and the control requirement
that forwarding is not receipt and releasing is not approval) and VI-C (separate timestamps per
stage).

Decided by the user on 2026-11-07. Each office forwards its own papers. The Office of the
Registrar forwards its papers only from the In Progress page (also 2026-11-07). The system tracks the paper, not a digital file. Until now,
an office's "Signed – release to next office" forwarded the document in the same click, so the
system said the paper had left an office while it was still on a desk there. From now on, an
office marking its task done keeps the paper in that office. The office then forwards it when
the paper actually leaves, and the next office accepts receipt when it arrives. The In Progress
page shows where each paper is now and where it goes next.

The same request also tidies the registrar's stage page (spec 16): Confirm and Reject buttons
that stand out, centred decision buttons, no hint under Remarks, and no ledger on the working
page.

This replaces:

- REQ-05-01's "forward the document to the first office" on acceptance.
- REQ-05-04's forwarding on approval, and REQ-05-07's sending back on correction.
- REQ-16-04's "tracking only, with no actions" and its list of statuses.
- REQ-16-07's ledger on the working page (it stays on the Records page).

## User stories

- **US-17-1** As an office, I want to mark my task done separately from sending the paper on, so
  that the system shows where the paper physically is.
- **US-17-2** As the registrar, I want to see where each paper is and where it goes next, and to
  forward the papers my office holds from that same page, so that I can follow and move them.
- **US-17-3** As the registrar, I want to see when a paper reached each office and when it left,
  so that I can measure each office's time.
- **US-17-4** As an evaluator, I want Confirm and Reject to stand out, so that I do not miss a
  part.

## Acceptance criteria

### The registrar's stage page
- **REQ-17-01** ON each checklist part THE SYSTEM SHALL show Confirm as a green button and Reject
  as a red button, both with white text at AA contrast. The chosen one SHALL be marked with a
  ring and `aria-pressed="true"`.
- **REQ-17-02** THE decision buttons (Approve, Return, Decline) SHALL be centred under the Remarks
  field.
- **REQ-17-03** THE Remarks field SHALL have no hint text. The rules stay the same: remarks are
  optional to approve and required to return or decline.
- **REQ-17-04** THE registrar's working page (`/registrar/requests/{id}`) SHALL NOT show the
  ledger. The Records page (`/registrar/records/{id}`) SHALL keep it.

### Where the paper is
- **REQ-17-05** WHEN a request is accepted THE SYSTEM SHALL place the paper at the Office of the
  Registrar. If the route starts there, its first office visit SHALL be received at once, by the
  registrar who accepted it, at the time of acceptance. No receipt step is needed. Otherwise
  the paper SHALL be forwarded to the route's first office, which accepts its receipt. The
  request stays `accepted_for_processing`.
- **REQ-17-06** WHEN staff of the office holding a received document mark it done (prepared,
  signed, stamped or corrected) THE SYSTEM SHALL record the time, the staff member and optional
  remarks, mark a route step completed, and keep the document in that office as "Done, not yet
  forwarded". It SHALL NOT forward the document or notify the next office.
- **REQ-17-07** WHILE a document is done and still in an office THE SYSTEM SHALL let staff of that
  office forward it to the next office: the next office on the route, or, for a corrected
  document, the office that returned it. Department staff forward from their request page, and
  registrars, for the Office of the Registrar, from the In Progress page (REQ-17-12). Forwarding
  SHALL record the time and staff member as
  the time the document left. It SHALL count the working days from receipt to leaving as the
  visit's time spent, open a visit at the next office awaiting receipt, and notify that office.
  Forwarding from the route's first office SHALL set the status `in_progress` (REQ-16-18). WHEN
  the next office is the same office, marking it done SHALL move the document on at once and
  receive it there, since the paper does not move.
- **REQ-17-08** THE next office SHALL still accept the receipt (REQ-05-03). Its receipt time is
  when the paper was delivered there.
- **REQ-17-09** WHEN the Registrar approves the final quality check (the route's last step) THE
  SYSTEM SHALL close the visit and mark the request `ready_for_release` at once. The paper stays
  at the Registrar for release.
- **REQ-17-10** THE SYSTEM SHALL let an office return a document for correction only while it is
  received and not yet done.

### In Progress
- **REQ-17-11** THE In Progress queue SHALL list the requests `accepted_for_processing`,
  `in_progress` or `returned_for_correction` whose paper is not at the final quality check. For
  each, it SHALL show the office holding the paper and its state there (on the way since, received
  since, or done since), the next office, the due date with an overdue mark, and a filter by
  office.
- **REQ-17-12** WHERE the Office of the Registrar holds a paper, its In Progress row SHALL show a
  "Forward to <next office>" button that forwards it (REQ-17-07) after a confirmation. This
  applies when the Registrar's task there is done, or while the paper is received there for the
  Registrar's own route task (preparation, or a correction). In the second case the button marks
  the task done and forwards the paper in one step. The button takes the place of the next
  office's name in the row, since it names that office. Rows the Office of the Registrar holds SHALL
  open the registrar's working page, and the other rows the read-only record, with no Forward.
- **REQ-17-13** THE registrar's working page SHALL show, for a paper the Office of the Registrar
  holds, the office it is at, its state there and the next office, with Accept receipt or Done as
  the state allows. It SHALL NOT offer Forward.
- **REQ-17-16** THE SYSTEM SHALL let department office staff forward only the done documents their
  own office holds, from their request page. Registrars SHALL forward only the documents the
  Office of the Registrar holds, and only from the In Progress page. Any other forward SHALL be
  refused (403).

### Records and queues
- **REQ-17-14** THE office visits table SHALL show for each visit when the paper was forwarded to
  the office, received (delivered), done and forwarded on (left), with the staff member for each,
  the time spent and the status.
- **REQ-17-15** THE office queue's "In office" tab, the registrar dashboard's office counts and
  the office reports SHALL count a done document still in the office as held by that office.
  The "In office" tab SHALL mark it "Done, not yet forwarded".
