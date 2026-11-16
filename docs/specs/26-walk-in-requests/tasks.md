# 26 — Walk-in Requests · Tasks

- [x] Migration: nullable `user_id`, `requester_email`, `recorded_by`; payment rows without a file
- [x] `RequestWorkflow::submitWalkIn`, `RecordWalkInRequest`, `WalkInRecorded` (REQ-26-04, 08)
- [x] `Registrar\WalkIn` page, first in the sidebar; shared section 1 and profile partials (REQ-26-01..03)
- [x] Walk-in payment panel and `verifyWalkInPayment`; a walk-in decline has no proof to reject (REQ-26-05)
- [x] Payment list without a file (REQ-26-06)
- [x] `ClaimWalkInRequests` on email verification and Google sign-in (REQ-26-07, 09)
- [x] No-owner safety: notifications, reminders, records, emails; Walk-in badge (REQ-26-09, 10)
- [x] Demo walk-in requests (REQ-26-11)
- [x] Pest tests for every REQ-26 criterion; registrar payment tests moved to the cashier or the walk-in panel
