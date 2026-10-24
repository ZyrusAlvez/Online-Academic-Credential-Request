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
