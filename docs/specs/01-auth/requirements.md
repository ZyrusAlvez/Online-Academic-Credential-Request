# 01 — Registration, Identity & Roles · Requirements

Source: *Proposed Workflow*, section III-A (registration and identity verification), IV
(identity of the document owner) and X (secure document storage).

## User stories

- **US-01-1** As a student, graduate or former student, I want to create an account with my name,
  email, contact number and password, so that I can request credentials online.
- **US-01-5** As a requester, I want to give my academic information and upload a valid ID once,
  so that the Registrar can confirm the account is really mine.
- **US-01-6** As the registrar, I want to review each requester's valid ID, so that credentials
  are only processed for their real owner.
- **US-01-2** As any user, I want to log in with only my email and password.
- **US-01-3** As the registrar, I want only verified, active accounts to use the system, so
  that requests and notifications reach real people.
- **US-01-4** As any user, I want to land on the home screen for my role after logging in.

## Acceptance criteria

### Registration
- **REQ-01-01** THE SYSTEM SHALL let a guest register with first name, middle name (optional),
  last name, email, contact number, password and password confirmation, and SHALL require and
  record their consent to the processing of their personal data (Data Privacy Act of 2012).
- **REQ-01-02** WHEN a guest registers THE SYSTEM SHALL create the account with role `student`,
  regardless of any role value submitted.
- **REQ-01-03** IF the email is already registered, the contact number is not exactly 11 digits
  (e.g. `09171234567`), consent is not given, or the password is not confirmed or fails the password rules
  (min. 8 characters) THEN THE SYSTEM SHALL reject the registration with a field error.
- **REQ-01-04** WHEN a student registers THE SYSTEM SHALL send an email verification link and
  log the student in.

### Login & session
- **REQ-01-05** THE SYSTEM SHALL authenticate users with email and password only.
- **REQ-01-06** IF a user fails to log in 5 times within a minute for the same email and IP THEN
  THE SYSTEM SHALL lock further attempts temporarily.
- **REQ-01-07** IF a deactivated user submits valid credentials THEN THE SYSTEM SHALL refuse the
  login with the message "This account has been deactivated. Please contact the Office of the
  Registrar."
- **REQ-01-08** WHEN an account is deactivated while its user is logged in THE SYSTEM SHALL log
  the user out on their next request.

### Verification & access
- **REQ-01-09** WHILE a user's email is unverified THE SYSTEM SHALL block access to application
  pages and show the verify-email notice.
- **REQ-01-10** WHEN a user opens `/dashboard` THE SYSTEM SHALL redirect them to their role's
  home: student → `/student`, registrar → `/registrar`, department → `/department`.
- **REQ-01-11** IF a user opens a page belonging to another role THEN THE SYSTEM SHALL respond 403.

### Profile
- **REQ-01-12** THE SYSTEM SHALL let users update their email in settings, and staff their first,
  middle and last name too (requesters edit their name in My Profile, REQ-01-22); changing the
  email SHALL reset its verification.
- **REQ-01-13** THE SYSTEM SHALL NOT let users delete their own account (requests form a
  permanent ledger; the registrar deactivates accounts instead).
- **REQ-01-14** WHEN a user's name is displayed THE SYSTEM SHALL format it as
  "First M. Last" (middle initial only when a middle name exists).

### Requester profile and identity
- **REQ-01-16** THE SYSTEM SHALL let a requester keep a profile with their first, middle (optional)
  and last name, contact number (exactly 11 digits, e.g. `09171234567`) and academic information: status (current student, graduate or former student), college, program or
  strand, student number (optional) and years attended (from and to, not before 1950 nor after the
  current year, from ≤ to).
- **REQ-01-17** THE SYSTEM SHALL let a requester upload a valid ID (type of ID and an image or PDF
  file of at most 5 MB), store it privately and mark their identity as pending verification;
  earlier uploads are kept.
- **REQ-01-18** WHILE a requester's name, contact number or academic information is incomplete,
  they have not given their data privacy consent, or no ID awaits or has passed verification THE SYSTEM SHALL send them to their profile instead of the new-request form.
- **REQ-01-19** THE SYSTEM SHALL let the registrar list requesters by identity status (pending
  first, oldest upload first), open the uploaded ID, and verify it or reject it with a reason; the
  requester SHALL be notified of the result and MAY upload another ID after a rejection.
- **REQ-01-20** THE SYSTEM SHALL let only the ID's owner and registrars open an uploaded ID
  (others get 404), without letting browsers cache it.
- **REQ-01-21** WHEN a requester submits a request THE SYSTEM SHALL take its academic details
  from their profile, shown on the new-request form and edited in My Profile.
- **REQ-01-22** THE SYSTEM SHALL save the whole My Profile page (name, contact number, academic
  information and, when one is chosen, a new valid ID) with a single "Save profile" button at the
  bottom right, and SHALL show the Data Privacy Act (RA 10173) notice there; WHILE the requester
  has not yet consented THE SYSTEM SHALL require them to tick the consent before saving and SHALL
  record when they did.

### Seed
- **REQ-01-15** THE SYSTEM SHALL provide a database seeder that creates an initial registrar
  account from `INITIAL_REGISTRAR_EMAIL` and `INITIAL_REGISTRAR_PASSWORD` so the system can be
  administered on first install. WHEN an account with that email already exists THE SYSTEM SHALL
  leave it unchanged. IF no password is configured and the account doesn't exist THEN THE SYSTEM
  SHALL refuse to seed.
