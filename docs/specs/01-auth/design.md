# 01 — Authentication & Roles · Design

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
  `SEED_REGISTRAR_PASSWORD` (default `password` outside production).

## Routes

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | /dashboard | dashboard | auth, verified |
| GET | /student | student.dashboard | auth, verified, role:student |
| GET | /registrar | registrar.dashboard | auth, verified, role:registrar |
| GET | /department | department.dashboard | auth, verified, role:department |
