# 16 — Registrar Stages · Tasks

- [ ] Migration `request_checks`, nullable `request_releases.id_presented`; enums `CheckStage`, `CheckItem`, `CheckResult`; model `RequestCheck` (REQ-16-22)
- [ ] `RequestWorkflow`: checks, approve / return / decline, resubmit, reopen, payment and final-check decisions, simplified release; remove college, compliance, clarification, bypass and the separate ID review (REQ-16-09..21)
- [ ] In progress once forwarded from the first office (REQ-16-18)
- [ ] Policy: `evaluate` lock, requester-only `cancel`, `resubmit` (REQ-16-09, 15, 21)
- [ ] Working page rebuilt per stage with `x-check-card` (REQ-16-10..14, 16, 17, 19, 20)
- [ ] Requester's pending-compliance panel and Resubmit (REQ-16-15)
- [ ] Stage queues, Records list and record page, sidebar; remove the Requests list (REQ-16-01..07)
- [ ] Remove every CSV download (REQ-16-08)
- [ ] Dashboard links, compliance report, demo data
- [ ] Superseded criteria marked in specs 04, 05, 07, 11, 15; overview updated
- [ ] Pest tests for every REQ-16 criterion; tests of removed behaviour removed
