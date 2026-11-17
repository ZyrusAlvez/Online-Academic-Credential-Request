# 28 — Requester's Sex and Maiden Name, Renamed Colleges · Design

## Data
- `App\Enums\Sex`: `male`, `female`, with `label()`.
- New migration `2026_11_18_000002_add_sex_and_maiden_name`: `sex` (string 10, nullable) and
  `maiden_name` (string 100, nullable) on `users` (the saved profile) and on `credential_requests`
  (the request's copy). Nullable: accounts and requests made before this change have none.
- `User::hasRequesterDetails()` also needs the sex.

## Form
- `RequesterProfileForm`: `sex` (required, `Rule::enum(Sex)`), `maiden_name` (nullable, max 100).
  A maiden name typed before switching to Male is dropped, not refused: saving a male profile
  clears `maiden_name`. The summary gains "Sex" and,
  for a woman, "Maiden name". `WalkInRequesterForm::details()` returns both.
- `partials/requester-profile-fields`: a Sex radio group (`wire:model.live`) beside the contact
  number; the Maiden name input renders only while `sex` is female.

## Workflow
- `RequestWorkflow::submit()`, `submitWalkIn()` and the Edit Request copy write `sex` and
  `maiden_name`; a change of either counts as an identity change (REQ-28-07), as does
  `VerifiedDetails` (Identity part, `personal` section).
- `ClaimWalkInRequests` fills an empty sex (and the maiden name) from the newest linked request.

## Pages
- Registrar request page, Identity check: rows "Sex" and "Maiden name" (when given).
- `x-request-details`: "Maiden name" row when given.
- Landing page step 2 and `AcademicStatus::label()` wording.

## Seeders
- `DepartmentSeeder::COLLEGES`: `SHS` Senior High School, `CBA` College of Business and Accountancy,
  `COC` College of Criminology (replacing BED, CBAA, CCJE). `ProgramSeeder`: `SHS` → STEM. Still 34
  programs. Existing databases keep their rows (seeders only add what is missing, REQ-23-02): they
  get the new list on their next `app:reset` / `app:reset-demo`, or the Admin renames them.
