# 01 — Authentication & Roles · Requirements

## User stories

- **US-01-1** As a student or alumnus, I want to create an account with my name, email and
  password, so that I can request credentials online.
- **US-01-2** As any user, I want to log in with only my email and password.
- **US-01-3** As the registrar, I want only verified, active accounts to use the system, so
  that requests and notifications reach real people.
- **US-01-4** As any user, I want to land on the home screen for my role after logging in.

## Acceptance criteria

### Registration
- **REQ-01-01** THE SYSTEM SHALL let a guest register with first name, middle name (optional),
  last name, email, password and password confirmation.
- **REQ-01-02** WHEN a guest registers THE SYSTEM SHALL create the account with role `student`,
  regardless of any role value submitted.
- **REQ-01-03** IF the email is already registered, or the password is not confirmed or fails
  the password rules (min. 8 characters) THEN THE SYSTEM SHALL reject the registration with a
  field error.
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
- **REQ-01-12** THE SYSTEM SHALL let users update their first, middle and last name and email
  in settings; changing the email SHALL reset its verification.
- **REQ-01-13** THE SYSTEM SHALL NOT let users delete their own account (requests form a
  permanent ledger; the registrar deactivates accounts instead).
- **REQ-01-14** WHEN a user's name is displayed THE SYSTEM SHALL format it as
  "First M. Last" (middle initial only when a middle name exists).

### Seed
- **REQ-01-15** THE SYSTEM SHALL provide a database seeder that creates an initial registrar
  account so the system can be administered on first install.
