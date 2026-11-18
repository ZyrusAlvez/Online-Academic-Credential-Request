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

## In Progress read-only (2026-11-18)

- [x] Spec: REQ-32-06, REQ-17-13 and REQ-16-19 amended
- [x] Remove `Registrar\Queue::acceptReceipt` and its In Progress button, and the final check's Accept receipt panel
- [x] Pest tests REQ-17-13 / REQ-32-06 updated

## REQ-32-10 No In Progress list (2026-11-18)

- [x] Spec: REQ-32-10; REQ-16-04, 26, REQ-17-11..13, REQ-32-02, 06 amended
- [x] `RegistrarQueue::InProgress` removed; `forRequest` returns null for a paper at an office (Records)
- [x] `/registrar/in-progress` redirects to Records, status "processing"
- [x] `StageQueue` and the queue view keep Outgoing only
- [x] Pest tests REQ-32-10; In Progress tests removed or moved to Records/Outgoing
