# 27 — Several Credentials in One Application · Tasks

## Data and workflow
- [x] `request_applications`, `application_id`, `item_no`; backfill (REQ-27-01, 02)
- [x] Tracking numbers `rcYY-NNNNN-N` (REQ-27-01)
- [x] `submit()` / `submitWalkIn()` with several credentials; total amount due (REQ-27-01, 03)
- [x] Lead-only actions, status copied to the others until acceptance (REQ-27-03, 05)
- [x] Each credential accepted with its own route (REQ-27-04)
- [x] Pest tests for REQ-27-01..05

## Pages
- [ ] `x-application-credentials`: the credentials, purposes, fees and total (REQ-27-09)
- [ ] Request Tracker: one row per application, search by any credential (REQ-27-06)
- [ ] Requester's page: application part, then a tab per credential (REQ-27-07)
- [ ] Records list and record with tabs (REQ-27-08)
- [ ] Evaluation, payment validation and payment instructions list the credentials (REQ-27-09)
- [ ] Pest tests for REQ-27-06..09

## Form (last branch)
