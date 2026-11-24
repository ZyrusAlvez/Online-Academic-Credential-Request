# 28 — Requester's Sex and Maiden Name, Renamed Colleges · Design

## Data
- `App\Enums\Sex`: `male`, `female`, with `label()`.
- New migration `2026_11_18_000002_add_sex_and_maiden_name`: `sex` (string 10, nullable) and
  `maiden_name` (string 100, nullable) on `users` (the saved profile) and on `credential_requests`
  (the request's copy). Nullable: accounts and requests made before this change have none.
- `User::hasRequesterDetails()` also needs the sex.
- 2026-11-20: migration `2026_11_20_000001_drop_maiden_name` drops `maiden_name` from both tables
  (REQ-28-04); `down()` adds the nullable column back, without the values.

## Form
- `RequesterProfileForm`: `sex` (required, `Rule::enum(Sex)`). The summary gains "Sex".
  `WalkInRequesterForm::details()` returns it.
- `partials/requester-profile-fields`: a Sex radio group (`wire:model.live`) beside the contact
  number; while `sex` is female, a small right-aligned "Maiden Name" caption sits under the Middle
  name and the Last name inputs (`data-maiden-caption`).

## Workflow
- `RequestWorkflow::submit()`, `submitWalkIn()` and the Edit Request copy write `sex`; a change
  counts as an identity change (REQ-28-07), as does `VerifiedDetails` (Identity part, `personal`
  section).
- `ClaimWalkInRequests` fills an empty sex from the newest linked request.

## Pages
- Registrar request page, Identity check: row "Sex".
- Landing page step 2 and `AcademicStatus::label()` wording.

## Seeders
- `DepartmentSeeder::COLLEGES`: `SHS` Senior High School, `CBA` College of Business and Accountancy,
  `COC` College of Criminology (replacing BED, CBAA, CCJE). `ProgramSeeder`: `SHS` → STEM. Still 34
  programs. Existing databases keep their rows (seeders only add what is missing, REQ-23-02): they
  get the new list on their next `app:reset` / `app:reset-demo`, or the Admin renames them.
