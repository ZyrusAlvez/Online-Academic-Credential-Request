# 24 — Department Pages · Tasks

- [x] `DepartmentQueue` enum, `DepartmentLink`, `VisitQueue::for` (search, filter, sort) (REQ-24-02..04)
- [x] Routes, sidebar with counts, `/department` and the former request address redirect (REQ-24-01, 06)
- [x] `Department\Queue`: search, credential type filter, sortable headers; Incoming checkboxes and Acknowledge receipt (REQ-24-02..05)
- [x] `Department\Requests\Show` under its list: details, verified proof, route, the action; goes back to its list after an action (REQ-24-06, 07)
- [x] `PaymentProofPolicy`: department staff open the verified proof of a request sent to them (REQ-24-08)
- [x] Remove `Department\Dashboard`, `x-visit-queue` and the delay reason recording (REQ-24-09)
- [x] Superseded criteria marked in specs 05, 06, 09, 17
- [x] Pest tests for every REQ-24 criterion; tests of removed behaviour removed

## To Forward (2026-11-15)

- [x] `DepartmentQueue::ToForward` (done visits); To Validate keeps the received ones only (REQ-24-01, 02)
- [x] To Forward list with Next, checkboxes and a bulk Forward, like Incoming (REQ-24-10)
- [x] Its request page offers Forward to <next office> (REQ-24-07)
- [x] Pest tests for REQ-24-10 and the changed REQ-24-01, 02, 07; REQ-17-15 test on To Forward
