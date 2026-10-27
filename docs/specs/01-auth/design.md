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
| privacy_consented_at | timestamp null | Data Privacy Act consent at registration |
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
- `App\Http\Middleware\EnsureRequesterProfileComplete` (alias `profile.complete`) on
  `student.requests.create` redirects to `student.profile` (REQ-01-18).
- `Student\Profile` saves through `RequesterProfileForm` in one call: name, contact, academic
  details, `privacy_consented_at` (first save only) and an optional new ID, which goes through
  `SubmitIdentityDocument` (REQ-01-22). `User::missingProfileItems()` lists what is still
  missing; `canRequestCredentials()` is true when that list is empty.
- Livewire: `Student\Profile` (`/student/profile`, "My Profile") and `Registrar\Identities\Index`
  (`/registrar/identities`, "Identity Checks" with a pending-count badge).
- Registration (Volt) adds the contact number and the consent checkbox.

## Code

- `App\Enums\Role` — `Student`, `Registrar`, `Department` (backed string) with `label()` and
  `homeRoute()`.
- `App\Models\User` — implements `MustVerifyEmail`; `role` and `is_active` are **not** mass
  assignable; `name` accessor = "First M. Last"; `initials()` from first + last;
  `hasRole(Role ...$roles)`.
- `App\Http\Middleware\EnsureUserHasRole` (alias `role`) — `role:registrar` etc., 403 otherwise.
- `App\Http\Middleware\EnsureUserIsActive` (appended to the `web` group) — logs out
  deactivated users and redirects to login with the deactivated message.
- Login Volt page: after credential check, reject inactive users (logout + validation error),
  still counting the attempt against the rate limiter.
- Register Volt page: first/middle/last name fields; `User::create()` never receives `role`.
- Profile Volt page: first/middle/last name; delete-account section removed.
- `/dashboard` → closure redirecting to `auth()->user()->role->homeRoute()`.
- Role homes: `App\Livewire\{Student,Registrar,Department}\Dashboard` at `/student`,
  `/registrar`, `/department` behind `auth`, `verified`, `role:*`. Content comes in later specs.
- Sidebar navigation is chosen by role.
- `DatabaseSeeder` creates `registrar@uphsl.edu.ph` with password from
  `INITIAL_REGISTRAR_PASSWORD` in production (renamed from `SEED_REGISTRAR_PASSWORD` for clarity;
  staging always uses `password`).

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | /dashboard | dashboard | auth, verified |
| GET | /student | student.dashboard | auth, verified, role:student |
| GET | /registrar | registrar.dashboard | auth, verified, role:registrar |
| GET | /department | department.dashboard | auth, verified, role:department |
