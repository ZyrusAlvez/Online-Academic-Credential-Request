# 17 — Physical Hand-offs and the Stage Page · Tasks

- [ ] Check card colours, centred decision row, no remarks hint, no ledger on the working page (REQ-17-01..04)
- [ ] Migration `office_visits.done_at`, `done_by`; `VisitStatus::Done`; `RequestEventType::Corrected`
- [ ] `RequestWorkflow`: received at the Registrar on acceptance, Done without forwarding, `forwardDocument`, `nextOffice` (REQ-17-05..10)
- [ ] Office actions (`ActsOnVisits`, `partials.visit-actions`) with Done and Forward
- [ ] In Progress queue with Currently at / Next and Forward for the Registrar's papers; working page office panel (REQ-17-11..13)
- [ ] Office visits table with Received / Done / Left; open-visit lists, dashboards and reports include `done` (REQ-17-14, 15)
- [ ] Demo data and test helpers forward after Done
- [ ] Superseded criteria marked in specs 05 and 16
- [ ] Pest tests for every REQ-17 criterion
