# 06 — Tracking, Notifications & Reminders · Tasks

- [x] Requester tracker and filtered history; registrar ledger and visits table (REQ-06-01..04)
- [x] Notification rules for requesters, offices and registrars (REQ-06-05..08)
- [x] Bell, notification page, queued delivery kept (REQ-06-09, 10)
- [x] Pest tests for every REQ-06 criterion
- [x] `reminder_logs`, `DueReminder` notification, `app:send-due-reminders` + schedule (REQ-06-11..14)
- [x] Pest tests for REQ-06-11..14
- [x] `users.email_notifications`, `SetEmailNotifications`, mail channels honour it (REQ-06-16)
- [x] Settings → Email notifications page for requesters (REQ-06-16)
- [x] Pest tests for REQ-06-16
- [x] Tracker as a step bar with icons, green progress line and milestone dates (REQ-06-01)
- [x] Pest tests for the changed REQ-06-01
- [x] `App\Support\OfficeRoute` (planned route of a credential, or a request's own steps with
      done/current/upcoming), shared with New Request's card; office list under the tracker (REQ-06-17)
- [x] Pest tests for REQ-06-17
- [x] Offices inside the step bar instead of a list under it (REQ-06-01, REQ-06-17, 2026-11-12)
- [x] Pest tests for the changed REQ-06-01 and REQ-06-17
- [x] `RequestEventType::requiresRequesterAction()`; `RequestActivity` mails every requester update,
      always mails [ACTION REQUIRED], mails staff, staff email variant (REQ-06-05..08, 16, 18, 19)
- [x] "Action required" badge in the bell and on the Notifications page (REQ-06-18)
- [x] Registrar-wide due/overdue reminders; office reminders to department staff; staff emails
      (REQ-06-11, 12, 20)
- [x] Email notifications page for registrars and department staff, with the [ACTION REQUIRED]
      note for requesters (REQ-06-16); Help updated
- [x] Pest tests for the changed REQ-06-05..08, 11, 12, 16 and the new REQ-06-18..20
- [ ] Default queue connection `deferred` (not from `.env`), tests on `sync`; drop the queue
      process from `composer dev` and `queue:restart` from the deploy; deployment docs without a
      worker (REQ-06-10, 2026-11-13)
- [ ] Pest test for the changed REQ-06-10
