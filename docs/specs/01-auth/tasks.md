# 01 — Registration, Identity & Roles · Tasks

- [x] Role enum; users migration (names, role, is_active); User model + factory states
- [x] Registration with first/middle/last name, always role student (REQ-01-01..04)
- [x] Login rejects deactivated users; active-user middleware (REQ-01-05..08)
- [x] Role middleware, `/dashboard` redirect, role home placeholders (REQ-01-09..11)
- [x] Profile name fields, remove delete-account (REQ-01-12..14)
- [x] Role-aware sidebar navigation
- [x] Registrar seeder (REQ-01-15)
- [x] Pest tests for every REQ-01 criterion; starter-kit auth tests ported to Pest
- [x] `composer qa` green
- [x] Registration: contact number and Data Privacy Act consent (REQ-01-01, 03)
- [x] Profile columns, `identity_documents`, enums (AcademicStatus, IdentityStatus, ReviewStatus)
- [x] My Profile page: academic information and ID upload (REQ-01-16, 17)
- [x] Profile gate on the new-request form; form pre-filled from the profile (REQ-01-18, 21)
- [x] Identity Checks page, review action, notification, private file route (REQ-01-19, 20)
- [x] Demo seeder profiles; `app:reset-demo` clears identity documents
- [x] Pest tests for REQ-01-16..21 and the changed REQ-01-01, 03; data dictionary updated
- [x] `composer qa` green (CI, deploy run on staging)
- [x] My Profile: name fields, data privacy notice and consent, one "Save profile" button that
      also uploads the ID; settings keeps names for staff only (REQ-01-12, 16, 18, 22)
- [x] Pest tests for REQ-01-22 and the changed REQ-01-12, 16, 17
- [x] Sign-up with email and password only; names nullable (REQ-01-01, 03)
- [x] Email verification by 6-digit code: table, send/confirm actions, 30 s resend cooldown,
      verify page, link route removed (REQ-01-04, 23)
- [x] Pest tests for REQ-01-23 and the changed REQ-01-01, 03, 04, REQ-09-07; data dictionary
- [x] Google sign-in/sign-up with Socialite; `google_id`, nullable password; Pest tests with a
      mocked provider (REQ-01-05, 24); data dictionary
- [x] Requesters land on New Request; `profile.complete` middleware replaced by a red banner on
      New Request and My Requests and a locked form (REQ-01-10, 18, REQ-04-01)
