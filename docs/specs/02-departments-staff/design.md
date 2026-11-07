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
- `Database\Seeders\RemoveCollegeOfLawSeeder` (REQ-02-18) — deletes the `college` with code
  `LAW`, in one transaction, through the query builder (the
  `RequestEvent` and `ActivityLog` models refuse deletes and updates): the requests that name it
  (`college_id`, a step, a visit, an event or a delay note) with all their rows and the
  `notifications` whose `data->request_id` is one of them; `users.college_id`/`program` → null;
  its staff accounts (log entries' `actor_id` → null; an account still referenced elsewhere is
  deactivated and detached instead); its programs and `workflow_steps`; `activity_logs.department_id`
  → null. Uploaded receipts and documents are deleted from `RequestWorkflow::proofDisk()` after
  the commit. Run by `ReplaceCollegeListSeeder` (deploys that have not replaced the list yet)
  and by the `remove_college_of_law` migration (deploys that only deactivated Law).
- Filter and pick lists use `Department::active()`, `CredentialType::active()` and
  `is_active` accounts (REQ-02-19): `Registrar\Queue` (In progress office filter),
  `Registrar\Records\Index`, `Registrar\Reports\Index`, `Registrar\Logs\Index`,
  `Registrar\Programs\Index`. A filter value already in the URL still applies.
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
