# UPHSL Credential Tracker

Online Academic Credential Request, Tracking & Monitoring for the UPHSL Office of the
Registrar. Students request credentials; departments sign them in sequence; the registrar
controls the flow. **It is a digital ledger only — no credential documents are transferred
digitally** (the only upload is the student's proof of payment).

## Stack

- Laravel 12 / PHP 8.5, MySQL 8.4
- Livewire 4 + Flux (free) + Volt (starter-kit auth pages only), Tailwind CSS 4
- Pest for tests (run against the staging database, never production)

## Spec-driven development

`docs/specs/` is the source of truth. Every feature follows this loop:

1. Write `docs/specs/NN-feature/requirements.md` (user stories + EARS acceptance criteria with
   IDs like `REQ-04-03`), `design.md`, `tasks.md` → commit (`docs(spec): ...`).
2. Implement; every acceptance criterion gets a Pest test whose name starts with its ID.
3. Tick `tasks.md`, run `composer qa` → commit (`feat(...): ...`).

If code and spec disagree, fix the spec first (and say why in the commit).

## Engineering standards

Load `laravel-ai-coding-standards` before any code change; it routes to the other skills.
Gates: `composer qa` green; `laravel-security` checklist for auth, uploads, payments.

## Project rules

- Business operations live in `app/Actions/<Domain>/`, one public `handle()` per class.
- All request status transitions are methods on `App\Services\RequestWorkflow` — never update
  `credential_requests.status` or `request_steps.status` anywhere else. It writes the
  `request_events` ledger, which is append-only (no updates, no deletes).
- Fixed sets of states are backed enums in `app/Enums`.
- New app screens are class-based Livewire components in `app/Livewire/{Student,Department,Registrar}`.
  The starter kit's Volt auth/settings pages stay as Volt.
- Authorization via policies + the `role` route middleware. Cross-user access returns 404/403, never data.
- Brand colors are Tailwind tokens in `resources/css/app.css`: `primary` #012265,
  `secondary` #86A7DD, `highlight` #0000F4 (the brand "accent"; Flux's `accent` is mapped
  to primary), `ink` #1F2937 text, `background` white. Light mode only.
- App timezone is `Asia/Manila` (single-campus system); dates are stored and shown in it.

## Commands

```bash
composer qa          # pint --test, phpstan (level 6), pest
composer fix         # apply pint
composer dev         # STAGING: server + queue + logs + vite on :8000
composer prod        # PRODUCTION: build assets, server + queue on :8001
```

## Environments

Exactly two: `staging` (database `uphsl_credential_tracker_staging`) and `production` (database
`uphsl_credential_tracker`). ONE `.env` serves both: `APP_ENV` (default `staging`) picks the
database (`DB_DATABASE_STAGING` / `DB_DATABASE_PRODUCTION`) and URL (`APP_URL_STAGING` /
`APP_URL_PRODUCTION`) in `config/`; debug is forced off and logging is `warning` in production.
Run any production command with the variable set, e.g. `APP_ENV=production php artisan migrate`.
`AppServiceProvider` refuses any other `APP_ENV`; the only exception is Laravel's internal
`testing` mode used by the test runner, which always points at the staging database. Receipt
files live in `storage/app/private/<env>`. Demo data and `app:reset-demo` are staging-only.

## Local setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # set DB_USERNAME/DB_PASSWORD and INITIAL_REGISTRAR_PASSWORD
php artisan app:reset-demo                              # staging: schema + demo data
APP_ENV=production php artisan migrate --seed           # production: schema + base data + first registrar
npm run dev
```

## Things to know

- Livewire 4 is installed with a starter kit written for v3: `config/livewire.php` sets
  `component_layout` to `components.layouts.app`.
- `tests/TestCase.php` calls `withoutVite()` so tests don't need a front-end build.
- Tests wipe the staging database (RefreshDatabase). `tests/TestCase.php` refuses to run unless the
  database name ends in `_staging`; the PHPUnit extension `Tests\Support\ReseedDemoData` runs
  `php artisan app:reset-demo` after every run to reload staging demo data (skipped when `CI` is set).
- Mail uses the `log` mailer locally — check `storage/logs/laravel.log` for verification emails.
- Notifications are queued (`QUEUE_CONNECTION=database`); run `composer dev` or `php artisan queue:work`.
- `php artisan db:seed --class=DemoSeeder` creates demo accounts (password `password`) and requests
  in every status; it runs only in staging.
- Request queries for the registrar list and CSV export share `App\Queries\RegistrarRequestFilter`;
  office queues use `App\Queries\StepQueue`.
