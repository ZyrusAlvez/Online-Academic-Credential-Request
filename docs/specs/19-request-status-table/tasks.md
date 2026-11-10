# 19 — Request Status Table · Tasks

- [ ] Migration: `request_statuses` table with the fourteen rows (REQ-19-01, 04)
- [ ] Foreign keys from `credential_requests.status`, `request_events.from_status`/`to_status`, `due_date_pauses.resume_status` (REQ-19-03)
- [ ] Pest: the table matches `RequestStatus` (REQ-19-02); unknown statuses are refused (REQ-19-03); the rows exist without seeding (REQ-19-04)
- [ ] Overview: the status table names `request_statuses`
