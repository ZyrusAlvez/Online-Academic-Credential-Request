# 29 — Credential List Changes and Per-Type Options · Design

## Data
- Migration `2026_11_18_000003_add_credential_options`:
  - `credential_types`: `asks_term_and_year` (bool, default false), `fee_per_term` (bool, default
    false), `free_for` (json list of `AcademicStatus` values, nullable).
  - `credential_requests`: `terms` (json list of `Term` values, nullable), `academic_year`
    (string 9, nullable, e.g. `2025-2026`).
- `App\Enums\Term`: `first`, `second`, `summer` → "1st Semester", "2nd Semester", "Summer".

## One fee rule
`CredentialType::feeFor(?AcademicStatus $status, int $terms = 1): ?string`. It returns null when
the type requires no payment or is free for the status. Otherwise it returns the fee, multiplied
by the number of terms when `fee_per_term` is on. Everything that prices a credential uses it:

- the details card
- `CredentialRequestForm::summary()`
- `RequestWorkflow::createApplication()` (New Request and Walk-in)
- Edit Request

A credential row's `requires_payment`, `fee` and `amount_due` come from it. The application's
total is the sum of the rows; when the total is nothing, the application skips payment.

## Academic years
`CredentialType::academicYears()`: from the current school year back to 1950-1951, newest first.
The school year starts in June.

## Form
- Each entry of `CredentialRequestForm` gains `terms` (list) and `academic_year`. Both are
  required while the type asks for them and dropped otherwise.
- The duplicate rule compares type and year: the same type is refused unless it asks for the year
  and the years differ.
- `summary(?AcademicStatus)` adds the rows "Terms" and "Academic year" and prices each credential
  with `feeFor()`.
- `partials/credential-entry` shows the checkbox group and the year select under the purpose
  (server-side `@if` on the chosen type).

## Admin
`CredentialTypeForm` and the Credential Types modal gain:

- the switch "Asks for the term and academic year"
- the switch "Fee is per term"
- the checkboxes "Free for"

The last two are shown only while "Requires payment" is on, and they are cleared when it is off.

## Seeders
`CredentialTypeSeeder` takes the new list (still 17 types). `DemoRequestSeeder` uses the new
names and adds a Semestral Report request. Existing databases keep their types (seeders only add
what is missing, REQ-23-02): they get the new list on their next reset, or the Admin edits them.
