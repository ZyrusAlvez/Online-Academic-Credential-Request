# 16 — Registrar Stages · Tasks

- [x] Migration `request_checks`, nullable `request_releases.id_presented`; enums `CheckStage`, `CheckItem`, `CheckResult`; model `RequestCheck` (REQ-16-22)
- [x] `RequestWorkflow`: checks, approve / return / decline, resubmit, reopen, payment and final-check decisions, simplified release; remove college, compliance, clarification, bypass and the separate ID review (REQ-16-09..21)
- [x] In progress once forwarded from the first office (REQ-16-18)
- [x] Policy: `evaluate` lock, requester-only `cancel`, `resubmit` (REQ-16-09, 15, 21)
- [x] Working page rebuilt per stage with `x-check-card` (REQ-16-10..14, 16, 17, 19, 20)
- [x] Requester's pending-compliance panel and Resubmit (REQ-16-15)
- [x] Stage queues, Records list and record page, sidebar; remove the Requests list (REQ-16-01..07)
- [x] Remove every CSV download (REQ-16-08)
- [x] Dashboard links, compliance report, demo data
- [x] Superseded criteria marked in specs 04, 05, 07, 11, 15; overview updated
- [x] Pest tests for every REQ-16 criterion; tests of removed behaviour removed
- [x] `CredentialRequest::staffWhereabouts()` on Records, the record page and the Admin dashboard (REQ-16-23)
- [x] Pest tests for REQ-16-23
- [x] Checklist parts' data as `x-summary-row`s, label left and value right (REQ-16-24)
- [x] Pest test for REQ-16-24
