# 24 — Department Pages: Incoming, To Validate, To Forward, History · Requirements

Source: *Proposed Workflow*, section VI-A (the receiving office accepts receipt, then processes
and reviews the document), VI-B (Accept Receipt, Approve / Release to Next Office, Return for
Correction, Forward Document) and VII (the routing example).

Decided by the user on 2026-11-15. A department office works from four lists in the sidebar,
in place of the one "Office Queue" page with tabs: **Incoming** (documents sent to the office,
which it acknowledges, several at once), **To Validate** (documents in the office to approve or
return, formerly "In our office"), **To Forward** (approved documents to send on, several at
once; added later the same day) and **History** (what the office did). A request opens under its list with only
what the office needs: the request details, the verified proof of payment and the route, then
the office's action. The office no longer records delay reasons: how long an office may keep a
document is not defined yet (user, 2026-11-15).

This replaces:

- REQ-05-02's single office queue with three tabs.
- REQ-05-09 (department delay reasons).
- REQ-06-04's office visits table and tracking history on the office's request page.

## User stories

- **US-24-1** As office staff, I want the documents sent to my office in one list that I can
  search, sort and filter, and to acknowledge several at once when they arrive, so that receiving
  a stack of papers is quick.
- **US-24-2** As office staff, I want the documents in my office in their own list, so that I see
  what I still have to sign, stamp or correct.
- **US-24-5** As office staff, I want the approved documents I still have to send on in their own
  list, and to forward several at once, so that sending a stack of papers is quick.
- **US-24-3** As office staff, I want a history of what my office did, so that I can answer
  "did we already sign this?".
- **US-24-4** As office staff, I want a request's page to show only its details, its verified
  payment and its route, with my action below them.

## Acceptance criteria

### The lists
- **REQ-24-01** THE sidebar of department staff SHALL show "Incoming", "To Validate", "To
  Forward" and "History", at `/department/incoming`, `/department/to-validate`,
  `/department/to-forward` and `/department/history`, with the number of documents in each of the
  first three beside them. `/department` SHALL lead to Incoming. There SHALL be no "Office Queue"
  page. *(To Forward added 2026-11-15, user's request.)*
- **REQ-24-02** Incoming SHALL list the documents forwarded to the office and not yet received;
  To Validate the documents received in the office and not yet approved or returned; To Forward
  the documents the office approved and has not yet forwarded (REQ-24-10); all oldest first and
  only for requests still being processed. Each SHALL have the columns Request (tracking number
  as a link, credential type), Requester and Since (when it was forwarded to the office, received
  there, or approved); To Forward also Next: a "Forward to <next office>" button (REQ-24-10).
- **REQ-24-03** History SHALL list the visits the office has closed (forwarded on, returned or
  bypassed), newest first, with the columns Request, Requester, Action (what the office did:
  e.g. "Signed", "Returned to <office>", "Corrected, sent back") and Date (when, and by whom).
- **REQ-24-04** EACH list SHALL have a search by tracking number or requester name (as Records
  searches, REQ-16-07), a filter by credential type, and sorting by clicking the Request,
  Requester or Since/Date column header, ascending then descending. Only those columns SHALL be
  sortable; any other sort value SHALL fall back to the list's default.
- **REQ-24-05** Incoming SHALL have a checkbox on each row and one in the header that selects
  every row on the page, and an "Acknowledge receipt" button that is disabled until at least one
  row is selected. After a confirmation it SHALL accept the receipt (REQ-05-03) of each selected
  document still on its way to the office, and say how many were acknowledged and how many were
  not (e.g. a colleague received it first). A selected id of another office's document SHALL be
  skipped, never received. To Validate and History SHALL have no checkboxes.
- **REQ-24-10** To Forward SHALL work like Incoming (REQ-24-05) with a "Forward" button: after a
  confirmation it SHALL forward each selected document the office approved and still holds to
  its next office (REQ-17-07), which then accepts its receipt, and say how many were forwarded
  and how many were not. A selected id of another office's document, or one not approved, SHALL
  be skipped, never forwarded. *(User's request, 2026-11-15: "the same feature as Incoming".)*
  Each row's Next column SHALL also be a "Forward to <next office>" button that forwards that one
  document after a confirmation, as the registrar's In Progress row does (REQ-17-12); Forward is
  offered only on this list, not on the request page (REQ-24-07). *(User's request, 2026-11-15.)*

### The request page
- **REQ-24-06** A request SHALL open under the list it is in for the office:
  `/department/incoming/{tracking no.}`, `/department/to-validate/…`, `/department/to-forward/…`
  or `/department/history/…`,
  with that list's sidebar item current and the list as the breadcrumb. Opened under another
  list, or at the former `/department/requests/{tracking no.}` (in notifications and emails), it
  SHALL redirect to the right one. WHEN an action moves the request out of the list it was opened
  under THE SYSTEM SHALL go back to that list with the action's message (e.g. Acknowledge receipt
  returns to Incoming, Approve or Return to To Validate). *(User's request, 2026-11-16: no longer
  follow the request to its new list.)* A request that has never been sent to the office SHALL
  respond 404.
- **REQ-24-07** THE request page SHALL show, in order: Request Details, Proof of Payment (only
  the verified proof; the section is left out when there is none) and Route, and below them the
  office's action: "Acknowledge receipt" under Incoming; under To Validate a "Decision" panel laid
  out like the registrar's (REQ-17-02, 03): the step's instructions, when it was received, Remarks
  with no hint, and one centred row with "Return" on the left (it opens the return dialog, REQ-05-06)
  and "Approve" on the right (it marks the office's task done, REQ-17-06, and moves the request to
  To Forward); nothing under To Forward (it is forwarded from its list, REQ-24-10) or History. It
  SHALL NOT show the office visits table or the tracking history. *(Approve and Return replace the task's done label, e.g. "Stamped", and
  "Return for correction" on the buttons; user's request, 2026-11-15. History still says what was
  done, e.g. "Stamped".)*
- **REQ-24-08** Department staff SHALL be able to open the verified proof of payment of a request
  sent to their office. A proof still under review or rejected, or one of a request never sent
  to their office, SHALL respond 404.

### Delay reasons
- **REQ-24-09** Department pages SHALL NOT offer "Record delay reason", and no one SHALL be able
  to record one. The Delay Analysis Report keeps the cause inferred from where the document is
  (REQ-07-18); reasons recorded before stay on the Records page and in the report. *(User's
  decision, 2026-11-15: per-office time limits are not defined yet.)*
