# 32 — The Registrar Carries the Papers · Tasks

- [x] Spec 32; REQ-05-06, REQ-17-07, 12, 16 and REQ-24-10 amended to point here
- [x] `returnVisit` keeps the paper (Done, pending return); `forwardDocument` sends a pending return back (REQ-32-07, 08)
- [x] `OfficeVisit::isPendingReturn`, `whereForwardable`, `nextStep` for a pending return
- [x] `OfficeVisitPolicy::forward`: registrars only, any office (REQ-32-04)
- [x] `RegistrarQueue::Outgoing`, `StageQueue`, route, row and bulk Forward; In Progress without Forward (REQ-32-02, 03, 06)
- [x] `DepartmentQueue::Outgoing` view-only, redirect from `/department/to-forward` (REQ-32-05)
- [x] Demo data forwards as the registrar
- [x] Pest tests REQ-32-01..08; existing tests updated (a freshly accepted request is in Outgoing, since the Registrar's preparation is finished through Forward, REQ-17-12)
- [x] `composer qa`

## REQ-32-09 (2026-11-18)

- [x] Spec: REQ-32-09; REQ-17-13, REQ-17-18 and REQ-32-06 amended
- [x] `RequestWorkflow::forward` receives at once a paper sent to the Office of the Registrar
- [x] In Progress row wording "Collected :time for :office, not yet received"
- [x] Pest tests REQ-32-09; existing tests updated
- [ ] `composer qa`
