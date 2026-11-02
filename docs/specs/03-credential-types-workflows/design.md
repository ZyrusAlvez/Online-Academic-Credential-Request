# 03 — Credential Master List & Routes · Design

## Data

`credential_types`

| Column | Type | Notes |
|---|---|---|
| name | string(150) unique | |
| description | text null | shown to requesters |
| requires_payment | boolean default false | |
| fee | decimal(10,2) null | per copy; payment happens on the UPHSL portal |
| processing_days | unsigned tinyint default 5 | processing period in **working days** (spec 10) |
| requirements | json null | list of documentary requirements shown before submitting and used by the evaluator (spec 04) |
| special_conditions | text null | shown before submitting |
| requires_receiving_institution | boolean default false | transfer credentials: the receiving school's complete name is required (spec 04) |
| is_active | boolean default true, index | |

`workflow_steps` (the route template)

| Column | Type | Notes |
|---|---|---|
| credential_type_id | FK cascade | |
| position | unsigned smallint | 1-based, contiguous; index (credential_type_id, position) |
| department_id | FK null, restrict | null ⇔ `is_student_college` (the Dean step) |
| is_student_college | boolean default false | resolves to the requester's college at routing |
| task | string(20) default `sign` | `App\Enums\RouteTask`: `prepare`, `sign`, `documentary_stamp` |
| instructions | string(255) null | |

The Registrar's final quality check is not a configurable step: spec 05 appends it to every
request's route, so no route can miss it. Until spec 05 lands, routes end at their last step.

## Code

- `App\Enums\RouteTask` with `label()` (Prepare, Sign, Documentary stamp) and `doneLabel()`.
- `App\Models\CredentialType` — `requirements` cast to array; `routeSummary()` ("Registrar →
  Dean → VP Finance → Chancellor") for lists and the request form.
- `App\Models\WorkflowStep` — `label()` returns the office name or "Dean (student's college)".
- Actions (`app/Actions/Workflow`): `AddWorkflowStep` (now takes a task), `MoveWorkflowStep`,
  `RemoveWorkflowStep`. Editing a step's task and instructions is a validated update.
- Livewire: `Registrar\CredentialTypes\Index` (table + modal form) and
  `Registrar\CredentialTypes\Workflow` (route builder).

## Seed: the master list (editable)

`Database\Seeders\CredentialTypeSeeder::run()` uses `firstOrCreate` (never overwrites);
`install()` replaces the 17 types and their routes and deactivates every other type. The data
migration `install_credential_master_list` runs `DepartmentSeeder` then `install()` on deploy
(skipped under the test runner).

| Credential | Fee | Days | Route (task) |
|---|---|---|---|
| Authentication | ₱50 | 1 | REG prepare |
| Certification, Authentication, Verification (CAV) | ₱500 | 2 | REG prepare |
| Certification (general); of Graduation; of Enrollment; of Units Earned; Course Description; Medium of Instruction; Copy of Grades; Notice of Acceptance; Letter for the BI Commissioner; Permit to Cross-Enroll; Transfer Certificate; Copy of Grades for Transfer Credentials | ₱230 | 6 | REG prepare → VPF documentary stamp → CHAN sign |
| Diploma / Certificate for Non-Degree Programs | ₱800 | 6 | REG prepare → CHAN sign → PRES sign |
| Transcript of Records (TOR); TOR for Transfer Credentials | ₱530 | 7 | REG prepare → Dean sign → VPF documentary stamp → CHAN sign |

Requirements (initial, for the Registrar to validate, *Proposed Workflow* XIV): every type
lists "Completed clearance"; the TOR, diploma, graduation and transfer types also list the
admission requirements (Form 137 or the UPHSL copy of the previous TOR, and a PSA-authenticated
birth certificate). Transfer types require the receiving school's name.

## Routes

| URI | Name |
|---|---|
| /registrar/credential-types | registrar.credential-types |
| /registrar/credential-types/{credentialType}/workflow | registrar.credential-types.workflow |
