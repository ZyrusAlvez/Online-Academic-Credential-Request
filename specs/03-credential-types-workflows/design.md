# 03 — Credential Types & Workflow Builder · Design

## Data

`credential_types`

| Column | Type | Notes |
|---|---|---|
| name | string(150) unique | |
| description | text null | shown to students |
| requires_payment | boolean default false | |
| fee | decimal(10,2) null | informational; payment happens on the UPHSL portal |
| processing_days | unsigned tinyint default 5 | target turnaround, used for overdue flags (spec 07) |
| is_active | boolean default true, index | |

`workflow_steps` (the template)

| Column | Type | Notes |
|---|---|---|
| credential_type_id | FK cascade | |
| position | unsigned smallint | 1-based, contiguous; index (credential_type_id, position) |
| department_id | FK null, restrict | null ⇔ `is_student_college` |
| is_student_college | boolean default false | resolves to the request's college at routing |
| instructions | string(255) null | |

No unique index on position: reordering swaps two rows inside a transaction instead.

## Code

- `App\Models\CredentialType` — `hasMany(WorkflowStep)->orderBy('position')`, `scopeActive`,
  `scopeRequestable` (active + has steps).
- `App\Models\WorkflowStep` — `belongsTo(CredentialType, Department)`, `label()` returns the
  department name or "Student's College".
- Actions (`app/Actions/Workflow`): `AddWorkflowStep`, `MoveWorkflowStep` (up/down swap),
  `RemoveWorkflowStep` (then resequence), `UpdateWorkflowStepInstructions`. Duplicate and
  multiple-college checks raise `ValidationException` so the UI shows a field error.
- `App\Policies\CredentialTypePolicy` — registrar only.
- Livewire: `Registrar\CredentialTypes\Index` (table + modal form, status toggle) and
  `Registrar\CredentialTypes\Workflow` (ordered list with ↑ ↓ ✕ buttons, add-step form,
  inline instructions edit). Up/down buttons instead of drag-and-drop for keyboard access.
- `Database\Seeders\CredentialTypeSeeder` — `firstOrCreate` by name; steps only created when
  the type is new.

### Default workflows (editable)

| Credential | Paid | Steps |
|---|---|---|
| Transcript of Records | yes | Library → Cashier/Finance → Student's College → Registrar |
| Certificate of Enrollment | no | Registrar |
| Certificate of Grades | no | Student's College → Registrar |
| Good Moral Certificate | no | Student Affairs → Registrar |
| Honorable Dismissal / Transfer Credentials | yes | Library → Cashier/Finance → Student Affairs → Student's College → Registrar |
| Diploma – Certified True Copy | yes | Cashier/Finance → Registrar |
| CAV – Certification, Authentication and Verification | yes | Student's College → Registrar |

Fees are left empty in the seed; the registrar fills them in.

## Routes

| URI | Name |
|---|---|
| /registrar/credential-types | registrar.credential-types |
| /registrar/credential-types/{credentialType}/workflow | registrar.credential-types.workflow |
