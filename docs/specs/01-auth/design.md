# 01 — Registration, Identity & Roles · Design

## Data

`users` (edit of the starter-kit create migration; project is pre-release):

| Column | Type | Notes |
|---|---|---|
| first_name | string(100) | |
| middle_name | string(100) null | |
| last_name | string(100) | |
| email | string unique | |
| role | string(20), index, default `student` | `App\Enums\Role` |
| is_active | boolean default true | |
| email_verified_at, password, remember_token, timestamps | | starter kit |

`department_id` is added in spec 02.

Requester profile and identity (added with the redesign):

| Column | Type | Notes |
|---|---|---|
| contact_number | string(20) null | required at registration; exactly 11 digits (`09171234567`), `RequesterProfileForm::PHONE_RULE` |
| privacy_consented_at | timestamp null | Data Privacy Act consent, given on the My Profile summary |
| academic_status | string(20) null | `App\Enums\AcademicStatus`: `current`, `graduate`, `former` |
| college_id | FK null → departments | the requester's college (the Dean on routes) |
| program | string(150) null | program or strand |
| student_number | string(30) null | optional |
| attended_from, attended_to | unsigned smallint null | years attended |
| identity_status | string(20) default `unsubmitted` | `App\Enums\IdentityStatus`: `unsubmitted`, `pending`, `verified`, `rejected`; not mass assignable |
| identity_verified_at | timestamp null | |

`identity_documents`: `user_id` FK, `id_type` (string 60), `path`, `original_name`, `mime_type`,
`size`, `status` (`App\Enums\ReviewStatus`: `pending`, `verified`, `rejected`), `reviewed_by` FK
null, `reviewed_at`, `remarks`, timestamps. Files go to `identity-documents/{user}/` on the private
receipts disk (`RequestWorkflow::proofDisk()`).

Identity code:

- `App\Actions\Identity\SubmitIdentityDocument` stores the file, adds the row and sets the user
  `pending` (deleting the file if the transaction fails).
- `App\Actions\Identity\ReviewIdentityDocument` verifies or rejects the latest pending document
  (reason required to reject), updates the user and sends `App\Notifications\IdentityReviewed`
  (database + mail).
- `IdentityDocumentController@show` streams the file with `no-store`; `IdentityDocumentPolicy`
  allows the owner and registrars, 404 for everyone else.
- No middleware guards `student.requests.create`: `<x-profile-required-callout>` shows the red
  banner (on New Request and My Requests) from `missingProfileItems()`, `Student\Requests\Create`
  renders sections after the credential picker `inert` with Submit disabled, and `submit()` plus
  `RequestWorkflow::submit()` refuse an incomplete profile (REQ-01-18).
- `Student\Profile` has two steps on one URL, because a chosen ID is a temporary upload held by
  the component and would not survive a move to another route. `review()` validates everything
  except the consent (`RequesterProfileForm::validateDetails()`) and shows the summary built by
  `RequesterProfileForm::summary()`; the form stays in the DOM, hidden, so "Back to edit" keeps
  the chosen file. `save()` runs only from the summary, in one call: name, contact, academic
  details, `privacy_consented_at` (first save only) and an optional new ID, which goes through
  `SubmitIdentityDocument`, all in one transaction (REQ-01-22, 25, 29). `User::hasLockedName()`
  locks the name of a verified requester: `RequesterProfileForm` puts the saved name back before
  validating, so a forged change is ignored. A registrar corrects it through
  `CorrectRequesterName` on Identity Checks (REQ-01-27, 28). `User::needsValidId()` (no ID, or
  the last one rejected) is the only state that offers the ID upload; `RequesterProfileForm`
  prohibits a file otherwise and `SubmitIdentityDocument` refuses it, so a pending or verified ID
  can't be replaced (REQ-01-17). `User::hasLockedProfile()` (identity pending or verified and
  nothing missing) turns the whole page into the read-only summary with a note; `review()` and
  `save()` answer 403 then. A rejection sets the status back to `rejected`, which opens the form
  again (REQ-01-30). The page shows no "Step N of 2" label (the user asked for it to
  go): a screen-reader-only heading still announces each step and takes focus when it changes.
  `User::missingProfileItems()` lists what is still
  missing; `canRequestCredentials()` is true when that list is empty.
- Livewire: `Student\Profile` (`/student/profile`, "My Profile") and `Registrar\Identities\Index`
  (`/registrar/identities`, "Identity Checks" with a pending-count badge).
- Registration (Volt) adds the contact number and the consent checkbox.

## Code

- `App\Enums\Role` — `Student`, `Registrar`, `Department` (backed string) with `label()` and
  `homeRoute()` (students: `student.requests.create`).
- `App\Models\User` — implements `MustVerifyEmail`; `role` and `is_active` are **not** mass
  assignable; `name` accessor = "First M. Last"; `initials()` from first + last;
  `hasRole(Role ...$roles)`.
- `App\Http\Middleware\EnsureUserHasRole` (alias `role`) — `role:registrar` etc., 403 otherwise.
- `App\Http\Middleware\EnsureUserIsActive` (appended to the `web` group) — logs out
  deactivated users and redirects to login with the deactivated message.
- Login Volt page: after credential check, reject inactive users (logout + validation error),
  still counting the attempt against the rate limiter.
- Register Volt page: first/middle/last name fields; `User::create()` never receives `role`.
- No settings Profile page (REQ-01-12): `/settings` opens Email notifications for requesters and Password for staff; delete-account section removed.
- `/dashboard` → closure redirecting to `auth()->user()->role->homeRoute()`.
- Role homes: `App\Livewire\{Student,Registrar,Department}\Dashboard` at `/student`,
  `/registrar`, `/department` behind `auth`, `verified`, `role:*`. Content comes in later specs.
  (The requester's list, Request Tracker, moved to `/student/requests` on 2026-11-11, and
  `/student` redirects there, REQ-09-21.)
- Sidebar navigation is chosen by role.
- `DatabaseSeeder` creates `registrar@uphsl.edu.ph` with password from
  `INITIAL_REGISTRAR_PASSWORD` in production (renamed from `SEED_REGISTRAR_PASSWORD` for clarity).
  Demo accounts use `@Test123` (REQ-07-09).

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | /dashboard | dashboard | auth, verified |
| GET | /student | student.dashboard | auth, verified, role:student |
| GET | /registrar | registrar.dashboard | auth, verified, role:registrar |
| GET | /department | department.dashboard | auth, verified, role:department |

## Email verification by code (REQ-01-01, 04, 23)

- `email_verification_codes`: `user_id` (unique, FK), `code_hash` (bcrypt), `expires_at`,
  `attempts`. `users.first_name`/`last_name` are nullable because sign-up asks only for email and
  password; `User::fullName()` and `initials()` fall back to the email, `User::greeting()` to
  "Hello,".
- `User::sendEmailVerificationNotification()` calls `App\Actions\Auth\SendVerificationCode`, so
  Laravel's `Registered` listener sends a code. The action is rate-limited per user (1 per 30 s,
  `RateLimiter` key `verification-code:{id}`) and sends `App\Notifications\VerificationCode`
  synchronously (not queued) so the code arrives while the user waits.
- `App\Actions\Auth\ConfirmVerificationCode` checks expiry, the 5-try cap and the hash under a row
  lock, then deletes the code, marks the email verified and fires `Verified`.
- The Volt `auth.verify-email` page holds the code form and the "Send a new code" button with an
  Alpine countdown; the signed-link route `verification.verify` and its controller are gone.

## Google sign-in (REQ-01-24)

- `laravel/socialite`; `config/services.php` `google` reads `GOOGLE_CLIENT_ID` and
  `GOOGLE_CLIENT_SECRET`, redirect `/auth/google/callback` (register `<APP_URL>/auth/google/callback`
  as the authorized redirect URI of the Google OAuth client, once per environment).
- `users.google_id` (nullable, unique); `users.password` nullable for accounts made through Google
  (they can set one with "Forgot your password?").
- Guest routes `auth.google.redirect` and `auth.google.callback` (throttled 10/min) on
  `Auth\GoogleController`, which 404s while the keys are missing; `App\Actions\Auth\SignInWithGoogle`
  finds by Google ID, then by email, else creates; it trusts only `email_verified === true`.
- `<x-google-sign-in>` renders the button on the log-in and sign-up pages when configured.

- Password rules (REQ-01-03) live in one place, `Password::defaults()` in `AppServiceProvider`:
  min. 8 characters, mixed case, a number and a symbol. Register, reset password, change password
  and the staff account form all use `Password::defaults()`. The rules only apply when a password
  is chosen, so existing accounts with an older, weaker password still log in. Demo accounts
  use `@Test123`, which meets the rules (REQ-07-09).
