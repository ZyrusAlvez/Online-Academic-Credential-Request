# UPHSL Credential Tracker

Online Academic Credential Request, Tracking & Monitoring for the UPHSL Office of the
Registrar. Students request credentials; departments sign them in sequence; the registrar
controls the flow. **It is a digital ledger only — no credential documents are transferred
digitally** (the only upload is the student's proof of payment).

## Stack

- Laravel 12 / PHP 8.5, MySQL 8.4
- Livewire 4 + Flux (free) + Volt (starter-kit auth pages only), Tailwind CSS 4
- Pest for tests (runs against the single app database `uphsl_credential_tracker`)

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
composer dev         # server + queue + logs + vite
```

## Local setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # set DB_* for MySQL
php artisan migrate --seed
npm run dev
```

## Things to know

- Livewire 4 is installed with a starter kit written for v3: `config/livewire.php` sets
  `component_layout` to `components.layouts.app`.
- `tests/TestCase.php` calls `withoutVite()` so tests don't need a front-end build.
- There is ONE database. Tests wipe it (RefreshDatabase); the PHPUnit extension
  `Tests\Support\ReseedDemoData` runs `php artisan app:reset-demo` after every run to reload
  demo data (skipped when `CI` is set). Data entered by hand is lost on each test run.
- Mail uses the `log` mailer locally — check `storage/logs/laravel.log` for verification emails.
- Notifications are queued (`QUEUE_CONNECTION=database`); run `composer dev` or `php artisan queue:work`.
- `php artisan db:seed --class=DemoSeeder` creates demo accounts (password `password`) and requests
  in every status; it refuses to run in production.
- Request queries for the registrar list and CSV export share `App\Queries\RegistrarRequestFilter`;
  office queues use `App\Queries\StepQueue`.
