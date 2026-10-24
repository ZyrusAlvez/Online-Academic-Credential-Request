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
- Livewire (registrar only, routes behind `role:registrar`):
  - `Registrar\Departments\Index` — table + Flux modal form (create/edit), toggle status.
  - `Registrar\Users\Index` — table + Flux modal form (create/edit staff), toggle status.
- Form objects: `App\Livewire\Forms\DepartmentForm`, `StaffForm` hold validation rules.

## Routes

| URI | Name | Component |
|---|---|---|
| /registrar/departments | registrar.departments | Registrar\Departments\Index |
| /registrar/users | registrar.users | Registrar\Users\Index |
