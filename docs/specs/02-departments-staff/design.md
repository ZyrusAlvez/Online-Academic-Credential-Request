# 02 — Offices & Staff Accounts · Design

## Data

`departments`

| Column | Type | Notes |
|---|---|---|
| id | bigint | |
| name | string(150) unique | |
| code | string(20) unique | upper-case, e.g. `CCS`, `VPF`, `REG` |
| type | string(20) index | `App\Enums\DepartmentType`: `college`, `office` |
| description | string(255) null | role in the workflow |
| is_active | boolean default true, index | |
| timestamps | | |

`users.department_id` — nullable FK → departments, `restrictOnDelete` (departments are never
deleted anyway). Set only for `department` staff.

The Office of the Registrar is seeded with code `REG` (`Department::REGISTRAR_CODE`); spec 05
lets registrar accounts act on its steps.

## Code

- `App\Enums\DepartmentType` (`College`, `Office`) with `label()`.
- `App\Models\Department` — `hasMany(User::class, 'staff')`, `scopeActive`, `scopeColleges`.
- `App\Models\User` — `belongsTo(Department)`.
- `Database\Seeders\DepartmentSeeder` — `updateOrCreate` by code; deactivates the retired codes
  `LIB`, `FIN`, `SAS` if present. Seeded offices: `REG` Office of the Registrar, `VPF` Office of
  the VP for Finance (documentary stamp), `CHAN` Office of the Chancellor, `PRES` Office of the
  President, plus the 11 colleges (Dean's review and signature). Deploys run it through the
  master-list data migration (spec 03).
- `App\Actions\Staff\SaveStaffAccount` — create/update staff; forces role ∈ {department,
  registrar}, clears `department_id` for registrars, marks new accounts verified.
- `App\Actions\Staff\SetAccountStatus` — refuses to deactivate the acting registrar.
- `App\Actions\Staff\ChangeAccountRole` — sets `role` and `department_id` of any account but the
  actor's (REQ-02-15); refuses a student with an open request (`RequestStatus::open()`,
  REQ-02-16) with a `ValidationException` on `role`. The student's profile, ID and requests stay,
  so a later switch back to student finds them again. The role middleware checks every request,
  so the change applies on the account's next page without signing it out. `SaveStaffAccount`
  no longer changes an existing account's role: the Edit form shows the role only when adding.
- `UserPolicy::changeRole` — registrar, and not their own account.
- `User::activityDescription` logs a role change as "Changed the role of …" (REQ-02-17); the
  old and new values come from the activity log's field changes.
- Livewire (registrar only, routes behind `role:registrar`):
  - `Registrar\Departments\Index` — table + Flux modal form (create/edit), toggle status.
  - `Registrar\Users\Index` — table + Flux modal form (create/edit staff), toggle status, and a
    Change role modal (`change-role`) opened from each row's menu.
- Form objects: `App\Livewire\Forms\DepartmentForm`, `StaffForm`, `RoleForm` hold validation rules.

## Routes

| URI | Name | Component |
|---|---|---|
| /registrar/departments | registrar.departments | Registrar\Departments\Index |
| /registrar/users | registrar.users | Registrar\Users\Index |
