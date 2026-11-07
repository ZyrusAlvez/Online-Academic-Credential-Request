# 17 — Physical Hand-offs and the Stage Page · Tasks

- [x] Check card colours, centred decision row, no remarks hint, no ledger on the working page (REQ-17-01..04)
- [x] Migration `office_visits.done_at`, `done_by`; `VisitStatus::Done`; `RequestEventType::Corrected`
- [x] `RequestWorkflow`: received at the Registrar on acceptance, Done without forwarding, `forwardDocument`; `OfficeVisit::nextStep`, `CredentialRequest::nextOffice` (REQ-17-05..10)
- [x] Office actions (`ActsOnVisits`, `partials.visit-actions`) with Done and Forward
- [x] In Progress queue with Currently at / Next and Forward for the Registrar's papers; working page office panel (REQ-17-11..13)
- [x] Office visits table with Received / Done / Left; open-visit lists, dashboards and reports include `done` (REQ-17-14, 15)
- [x] Demo data and test helpers forward after Done
- [x] Superseded criteria marked in specs 05 and 16
- [x] Pest tests for every REQ-17 criterion

## The Registrar forwards from In Progress (2026-11-07)

- [x] `forwardDocument` also takes the Registrar's received own task; `OfficeVisit::isForwardable`; `OfficeVisitPolicy::forward` (REQ-17-12, 16)
- [x] In Progress shows Forward for the Registrar's papers; offices keep Forward on their page; none on the working page (REQ-17-12, 13, 16)
- [x] Tests for REQ-17-07, 12, 13, 16

## Decisions always clickable, the good action on the right (2026-11-07)

- [x] Reject then Confirm on each part; Decline, Return, Approve in the decision row (REQ-17-01, 02)
- [x] Decision buttons always enabled; the `decision-blocked` dialog lists why an action can't run (REQ-17-17)
- [x] Pest tests for REQ-17-01, 02 and 17; spec 16 tests expect the dialog instead of the old notice
