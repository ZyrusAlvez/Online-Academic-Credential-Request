# 16 — Registrar Stages: Checklists, Evaluation Lock, Stage Queues · Requirements

Source: *Proposed Workflow*, sections III-B (the evaluator's checks and the evaluation results),
IV (authorization of representatives), V (payment verification and acceptance), VI (the final
quality check) and VIII-B (release).

Decided by the user on 2026-11-06. The registrar's request page put every action side by side
and let staff change what the requester entered (the college). Each Registrar stage is now its
own queue and a checklist. Every part of the checklist is confirmed or rejected, then the stage
ends with one decision (approve, return or decline) and remarks. One evaluator owns a request
while evaluating it. The single Requests list becomes the stage queues plus a view-only Records
list.

This replaces:

- REQ-04-06, 07, 09, 10, 11 and 15: the four evaluation results, deficiencies, clarification
  and the college correction.
- REQ-04-16's registrar cancellation.
- REQ-05-03's "first receipt puts it in progress", REQ-05-09's registrar delay reasons, and
  REQ-05-10 and 13 (bypass and pauses).
- REQ-07-02, 05 and 07's request list, and REQ-07-08 and 20's CSV downloads.
- REQ-11-03's separate review, and REQ-11-06 and 07's recipient name, ID and checkboxes.
- REQ-15-09, 10 and 12: the separate ID verification and the "ID to verify" mark.

## User stories

- **US-16-1** As an evaluator, I want to check each part of a request on a checklist and then
  approve, return or decline it, so that every request is judged the same way.
- **US-16-2** As an evaluator, I want a request to be mine while I evaluate it, so that two of us
  never act on it at once.
- **US-16-3** As the registrar, I want one queue per stage (evaluation, payment, offices, final
  check, release), so that I see only what needs my stage.
- **US-16-4** As a requester whose request was returned, I want to see what was rejected and why,
  fix it and resubmit, so that my request is evaluated again.
- **US-16-5** As the registrar, I want what the requester entered to stay as they entered it, so
  that the request is a faithful record of what was asked for.

## Acceptance criteria

### Navigation and queues
- ~~REQ-16-01~~ *Superseded by REQ-18-03:* the registrar's sidebar items are Initial Evaluation,
  Payment Validation, In Progress, Final Quality Check, Release and Records. Dashboard and
  Reports are the Admin's (REQ-18-02). THE SYSTEM SHALL NOT have the Requests list;
  `/registrar/requests` SHALL respond 404.
- **REQ-16-02** THE Initial Evaluation queue SHALL list the submitted requests and the requests
  under evaluation, oldest first, showing who is evaluating each one. A "Waiting for the requester"
  view SHALL list the requests pending compliance. *Changed by REQ-20-10:* it also lists the
  requests returned for clarification.
- **REQ-16-03** THE Payment Validation queue SHALL list the requests whose proof of payment awaits
  verification, oldest first.
- **REQ-16-04** THE In Progress queue SHALL list the requests in progress or returned for correction
  between offices (not those sent for the final check), with the office holding the document now,
  whether it has been received there, since when, the due date and an overdue mark, and SHALL let
  the registrar filter it by office. It SHALL be for tracking only, with no actions. *Changed by
  REQ-17-11, 12:* it also lists accepted requests, shows the next office, and lets the registrar
  forward the papers its office holds.
- **REQ-16-05** THE Final Quality Check queue SHALL list the requests whose document is sent to, or
  held by, the Registrar for the final quality check, oldest first.
- **REQ-16-06** THE Release queue SHALL list the credentials ready for release, oldest first.
- **REQ-16-07** THE Records list SHALL show every request, searchable by tracking number, student
  number or name, and filterable by status (including "Overdue"), credential type and the office
  holding it. A record SHALL open a read-only page with the request's details, route, office visits
  and ledger, and no actions. The working page does not show the ledger (REQ-17-04).
- **REQ-16-23** WHERE staff see where a request is (the Records list's *Currently at* column, a
  record's "Where it is now" and the Admin dashboard), THE SYSTEM SHALL name the requester instead
  of addressing them: "Waiting for the requester to complete the requirements", "Waiting for the
  requester's response" and "Waiting for the requester's payment". The requester's own pages and
  emails keep "you" and "your". *Changed by REQ-20-11:* the wording of the two returns.
- **REQ-16-08** THE SYSTEM SHALL NOT offer a CSV download of the requests or of any report; the
  former download addresses SHALL respond 404.

### The evaluation lock
- **REQ-16-09** WHEN a registrar starts the evaluation of a submitted request THE SYSTEM SHALL make
  them its evaluator. WHILE the request is under evaluation THE SYSTEM SHALL let only its evaluator
  open its working page and act on it. Any other registrar SHALL see only "<tracking number> is under
  evaluation by <name>", and their actions SHALL be refused (403). The lock SHALL end when the
  evaluator approves, returns or declines the request.

### Initial Evaluation
- **REQ-16-10** WHILE a request is under evaluation THE SYSTEM SHALL show its evaluator a checklist
  of parts, each with the request's own data, read-only:
  - Credential request: credential, purpose, receiving school, amount due, how the requester
    transacts.
  - User identity: name, email, contact number and the valid ID, which opens in the file viewer.
  - Academic record: status and years, student number, college, program or strand.
  - Representative, only when the requester transacts through a representative: each
    representative and their SPA and IDs.

  Each part SHALL have Confirm and Reject buttons on its right, show its current result, and stay
  changeable until the decision. THE SYSTEM SHALL NOT let the registrar change anything the
  requester entered.
- **REQ-16-11** BELOW the checklist THE SYSTEM SHALL show a Remarks field and the buttons Approve,
  Return to requester and Decline. Remarks SHALL be optional to approve and required to return or
  decline. The field shows no hint about this (REQ-17-03). *Changed by REQ-20-02:* Initial
  Evaluation has four buttons; Return to requester is now Return for Clarification and Require
  Office Visit.
- **REQ-16-12** WHEN the evaluator approves THE SYSTEM SHALL require every part shown to be
  confirmed. It SHALL then mark the request's ID and its representatives verified, and move a paid
  request to `awaiting_payment` with payment instructions, or accept a free request directly
  (REQ-04-14).
- ~~REQ-16-13~~ *Superseded by REQ-20-03 and 04 (2026-11-10):* returning to the requester is now
  Return for Clarification (`returned_for_clarification`, fixed online) or Require Office Visit
  (`pending_compliance`, complied with in person).
- **REQ-16-14** WHEN the evaluator declines the request THE SYSTEM SHALL set the status `declined`
  with the remarks as the reason and notify the requester.
- ~~REQ-16-15~~ *Superseded by REQ-20-05..08 (2026-11-10):* only a request returned for
  clarification is edited and resubmitted online; one pending compliance waits for the office
  visit.
- ~~REQ-16-16~~ *Superseded by REQ-20-09 and 13 (2026-11-10):* there is no Reopen evaluation; the
  registrar marks the missing items received and confirms compliance.

### Payment Validation
- **REQ-16-17** WHILE a request's proof of payment awaits verification THE SYSTEM SHALL show the
  checklist parts Proof of payment (the uploaded file, an image shown as a picture) and Amount and
  reference (amount due, amount paid, reference and date paid), the official receipt number field,
  Remarks, and the buttons Approve, Return and Decline:
  - Approve SHALL require both parts confirmed and the official receipt number; it verifies the
    payment and accepts the request (REQ-04-14).
  - Return SHALL require remarks; it rejects the proof and sends the request back to
    `awaiting_payment`.
  - Decline SHALL require remarks; it declines the request.

### In progress
- **REQ-16-18** THE SYSTEM SHALL keep an accepted request `accepted_for_processing` while the
  document is at its first office (the Office of the Registrar's preparation). WHEN the document is
  forwarded to the next office THE SYSTEM SHALL set the status `in_progress`.

### Final Quality Check
- **REQ-16-19** WHILE a request's document is at the final quality check THE SYSTEM SHALL first let
  the registrar accept its receipt (REQ-05-03). Once it is received, THE SYSTEM SHALL show the
  checklist parts Credential document (complete and correct) and Signatures and stamps (each office
  on the route and when it finished), Remarks, and the buttons Approve and Decline:
  - Approve SHALL require both parts confirmed; it marks the request `ready_for_release`.
  - Decline SHALL require remarks; it closes the office visit, declines the request and notifies
    the requester.

### Release
- **REQ-16-20** WHILE a credential is ready for release THE SYSTEM SHALL let the registrar mark it
  released, choosing who claimed it: the owner, or a verified representative of the request. The
  recipient's name SHALL be filled in from them, and the release time and the staff member
  recorded. A representative declared after the evaluation SHALL be verifiable or rejectable, with
  a reason, on this page.

### Removed registrar actions
- **REQ-16-21** THE SYSTEM SHALL NOT let the registrar change a request's college, cancel a
  request, bypass an office, return a request for customer action (pause it) or record a delay
  reason; none of these SHALL appear on the registrar's pages. Department offices keep their own
  actions (spec 05), and requesters keep cancelling before acceptance.

### Checklist record
- **REQ-16-22** THE SYSTEM SHALL store each checklist part's result per request, with the staff
  member and the time, and SHALL write every decision, with the rejected parts and the remarks,
  to the request's ledger.
