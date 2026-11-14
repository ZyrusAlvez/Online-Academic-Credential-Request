# 23 — Database Baseline and Reset · Design

## What each folder is for

| Folder | Holds | Runs | Same everywhere? |
|---|---|---|---|
| `database/migrations` | The structure: tables, columns, keys, and the fixed request statuses | Every deploy (`php artisan migrate`), each file once per database | Yes |
| `database/seeders` | The starting content: base lists and the first Admin; `DemoSeeder` adds demo data | Only on a new or reset database | Base data yes; demo data local and staging only |
| `database/factories` | Fake rows for the automated tests | Tests only | Not used on servers |

## Migrations: 38 files to 16

Each kept file creates its tables in their final shape. Nothing drops, rebuilds, alters or copies.

**The kept files reuse existing file names.** Laravel records which migrations ran by file name.
Staging and production have already run every one of these names, so `php artisan migrate` finds
nothing new there and deploys go on working with no manual step. The rows of the deleted files stay
in their `migrations` table, unused, until the reset.

| File | Creates |
|---|---|
| `0001_01_01_000000_create_users_table` | `users` (account, saved profile), `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_10_11_000001_create_departments_table` | `departments`, and the keys from `users.department_id` and `users.college_id` to it |
| `2026_10_11_000003_create_credential_types_table` | `credential_types`, `workflow_steps` |
| `2026_10_11_000004_create_credential_requests_tables` | `identity_documents`, `credential_requests`, `request_deficiencies`, `payment_proofs`, `request_steps`, `office_visits`, `delay_notes`, `due_date_pauses`, `request_representatives`, `request_documents`, `releases`, `request_events` |
| `2026_10_11_133940_create_notifications_table` | `notifications` |
| `2026_10_24_000002_create_non_working_days_table` | `non_working_days` |
| `2026_10_24_000008_create_reminder_logs_table` | `reminder_logs` |
| `2026_10_27_000001_create_email_verification_codes_table` | `email_verification_codes` |
| `2026_11_02_000001_create_programs_table` | `programs` |
| `2026_11_05_000001_create_purposes_table` | `purposes` |
| `2026_11_06_000001_create_activity_logs_table` | `activity_logs` |
| `2026_11_06_000004_create_request_checks_table` | `request_checks` |
| `2026_11_10_000002_create_request_statuses_table` | `request_statuses` with its 15 rows, and the keys from every status column to it |
| `2026_11_11_000001_create_requester_verifications_table` | `requester_verifications` |

`users` is created before `departments`, so its two department columns are plain columns there and
get their foreign keys in the departments file.

**Check done when this was built:** a database built from the old 38 files and one built from the
16 were compared table by table (`SHOW CREATE TABLE`, plus the `request_statuses` rows). Columns,
types, defaults, column order, indexes and foreign keys are identical.

## Seeders: 11 to 8

- Removed: `ReplaceCollegeListSeeder`, `RemoveCollegeOfLawSeeder`, `BasicEducationAccountSeeder`
  (one-time changes to databases that existed before 2026-11-07), `CredentialTypeSeeder::install()`,
  `ProgramSeeder::RENAMES`, `DepartmentSeeder::RETIRED`.
- `DatabaseSeeder` is the one list of base data: `DepartmentSeeder`, `ProgramSeeder`,
  `PurposeSeeder`, `CredentialTypeSeeder`, `AdminSeeder`, `NonWorkingDaySeeder`.
- Every base seeder uses `firstOrCreate`, so seeding again only adds what is missing (REQ-23-03).
  `DepartmentSeeder` and `NonWorkingDaySeeder` used `updateOrCreate` before, which would have
  overwritten the Admin's edits.
- The lists no longer arrive through "install" migrations. A later change to a starter list is made
  by the Admin in the app.

## Reset commands

- `App\Console\Commands\ResetApp` (`app:reset`):
  1. refuses without `config('uphsl.initial_admin.email' / '.password')`;
  2. in production, `ask()`s for the phrase `reset production`;
  3. deletes `payment-proofs`, `identity-documents` and `request-documents` on
     `RequestWorkflow::proofDisk()` (that disk is foldered per environment);
  4. calls `migrate:fresh --seed --force`.
- `App\Console\Commands\ResetDemo` (`app:reset-demo`): local and staging only; calls `app:reset`,
  then seeds `DemoSeeder`.

A reset deletes every account. The first Admin is recreated from `.env`; the Admin then creates the
registrar and department staff accounts again.
