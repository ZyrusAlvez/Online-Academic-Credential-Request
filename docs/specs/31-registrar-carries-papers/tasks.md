# 31 — The Registrar Carries the Papers · Tasks

- [ ] Spec 31; REQ-05-06, REQ-17-07, 12, 16 and REQ-24-01, 02, 06, 07, 10 amended to point here
- [ ] `returnVisit` keeps the paper (Done, pending return); `forwardDocument` sends a pending return back (REQ-31-07, 08)
- [ ] `OfficeVisit::isPendingReturn`, `whereForwardable`, `nextStep` for a pending return
- [ ] `OfficeVisitPolicy::forward`: registrars only, any office (REQ-31-04)
- [ ] `RegistrarQueue::Outgoing`, `StageQueue`, route, row and bulk Forward; In Progress without Forward (REQ-31-02, 03, 06)
- [ ] `DepartmentQueue::Outgoing` view-only, redirect from `/department/to-forward` (REQ-31-05)
- [ ] Demo seeder forwards as the registrar
- [ ] Pest tests REQ-31-01..08; existing tests updated
- [ ] `composer qa`
