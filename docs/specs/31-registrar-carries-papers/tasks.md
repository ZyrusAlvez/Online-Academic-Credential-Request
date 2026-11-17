# 31 — The Registrar Carries the Papers · Tasks

- [x] Spec 31; REQ-05-06, REQ-17-07, 12, 16 and REQ-24-10 amended to point here
- [x] `returnVisit` keeps the paper (Done, pending return); `forwardDocument` sends a pending return back (REQ-31-07, 08)
- [x] `OfficeVisit::isPendingReturn`, `whereForwardable`, `nextStep` for a pending return
- [x] `OfficeVisitPolicy::forward`: registrars only, any office (REQ-31-04)
- [x] `RegistrarQueue::Outgoing`, `StageQueue`, route, row and bulk Forward; In Progress without Forward (REQ-31-02, 03, 06)
- [x] `DepartmentQueue::Outgoing` view-only, redirect from `/department/to-forward` (REQ-31-05)
- [x] Demo seeder forwards as the registrar
- [x] Pest tests REQ-31-01..08; existing tests updated (a freshly accepted request is in Outgoing, since the Registrar's preparation is finished through Forward, REQ-17-12)
- [x] `composer qa`
