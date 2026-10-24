# UPHSL Credential Tracker

Online Academic Credential Request, Tracking & Monitoring for the UPHSL Office of the
Registrar. Requesters (students, graduates, former students) request credentials; the
Registrar's Office evaluates, verifies payment and accepts them; offices (Registrar, Dean,
VP Finance, Chancellor, President) receive, process and release the document in sequence.
**It is a digital ledger only — no credential documents are transferred digitally.** The only
uploads are the requester's valid ID, a representative's SPA and IDs, and the proof of payment,
all private and staff-only.

The client's `docs/Proposed-Workflow-and-Process-Flow-for-the-Capstone-System.docx` defines the
target workflow; `docs/specs/00-overview.md` restates it (statuses, offices, assumptions).

## Stack

- Laravel 12 / PHP 8.5, MySQL 8.0 (server and CI; 8.4 works locally)
- Livewire 4 + Flux (free) + Volt (starter-kit auth pages only), Tailwind CSS 4
- Pest for tests (run against their own `uphsl_credential_tracker_testing` database)

## Spec-driven development

`docs/specs/` is the source of truth. Every feature follows this loop:

1. Write `docs/specs/NN-feature/requirements.md` (user stories + EARS acceptance criteria with
   IDs like `REQ-04-03`), `design.md`, `tasks.md` → commit (`docs(spec): ...`).
2. Implement; every acceptance criterion gets a Pest test whose name starts with its ID.
3. Tick `tasks.md`, run `composer qa` → commit (`feat(...): ...`).

If code and spec disagree, fix the spec first (and say why in the commit).

## Git workflow (every change, every session)

Work happens on a branch that is merged straight into `staging`; the only pull request is
`staging` → `production`. Never commit on `staging` itself, never push to `production`, and never
force-push either.

1. Start from fresh staging: `git switch staging && git pull --ff-only`, then
   `git switch -c <type>/<short-name>` (`feat/`, `fix/`, `docs/`, `ci/`, `chore/`). One feature or
   fix per branch.
2. Commit on the branch, following the spec-driven loop above, and push it
   (`git push -u origin <branch>`). Running `composer qa` locally is optional: CI runs it.
3. When the branch is done, merge it into `staging` (`git switch staging && git merge --no-ff
   <branch>`) and push `staging`. That starts the `deploy` run, which runs `qa` first and deploys
   staging only if it passes. Watch it until it succeeds (if `qa` fails, fix on the branch and merge
   again), then give the user the staging URL to test.
4. Open a pull request from `staging` into `production` describing what it releases (or update the
   open one). **Don't merge it.** The user tests staging and merges it, which deploys production.

`gh` isn't installed. Use the GitHub REST API (`api.github.com/repos/ZyrusAlvez/Online-Academic-Credential-Request`)
with the stored git credential (`printf 'protocol=https\nhost=github.com\n\n' | git credential fill`),
and never print the token. Useful calls: `POST /pulls`, `GET /commits/{sha}/check-runs`,
`PUT /pulls/{n}/merge` (`merge_method: merge`), `GET /actions/runs?branch=staging`.

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
composer dev         # server + queue + logs + vite on :8000
scripts/deploy-staging.sh      # manual deploy fallback (deploy-production.sh; .bat on Windows)
```

Branches: feature branches (from `staging`) → `staging` (staging server) → `production` (production
server, the default branch; there is no `main`). Branches merge into `staging` directly; only
`staging` → `production` is a pull request. CI (`.github/workflows/ci.yml`, PHP 8.5 + MySQL 8.0) runs on every PR and before every deploy;
`deploy.yml` deploys on every push to `staging` and to `production`, after `qa` passes (no reviewer
approval). Deploys SSH in with environment-specific keys whose forced
command is `/usr/local/bin/uphsl-deploy <env>` (from `scripts/server/uphsl-deploy`), which refuses
a production deploy that staging isn't already running. Never commit straight to `staging` or
`production`; merge branches into them. Docs: `docs/deployment/02-environments.md`.

## Environments

Exactly three `APP_ENV` values: `local` (a developer's machine), `staging` and `production` (each
in its own folder on the server). `AppServiceProvider` refuses any other value; the only exception
is Laravel's internal `testing` mode used by the test runner.

Each deployment has its own small `.env` (see `.env.example`): only per-deployment values and
secrets. Everything that follows from `APP_ENV` is a default in `config/`:

| | local | staging | production |
|---|---|---|---|
| Address (`APP_URL` overrides) | `http://localhost:8000` | `https://staging.18-139-77-227.sslip.io` | `https://18-139-77-227.sslip.io` |
| Database (`DB_DATABASE` overrides) | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Debug (`APP_DEBUG` overrides, except production) | on | on | always off |
| Log level | debug | debug | warning |
| Mailer | `smtp` once `MAIL_USERNAME` is set, else `log`; **every email redirected to your inbox** | `smtp` once set, else `log` | `smtp` once set, else `log` |
| Receipts folder (local disk or R2) | `local/` | `staging/` | `production/` |
| Demo data / `app:reset-demo` | allowed | allowed | refused |

The addresses live in `config/app.php`, so changing a site name is a code change plus a deploy.
Session cookies are secure automatically when the address is `https://`. Server setup:
`docs/deployment/01-deploy-on-aws-lightsail.md`.

## Local setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # set DB_*, INITIAL_REGISTRAR_* (and MAIL_* for real email)
php artisan app:reset-demo                              # schema + base data + demo data
npm run dev
```

MySQL needs two databases for the local user: `uphsl_credential_tracker_local` (the app) and
`uphsl_credential_tracker_testing` (the tests).

## Things to know

- Livewire 4 is installed with a starter kit written for v3: `config/livewire.php` sets
  `component_layout` to `components.layouts.app`.
- `tests/TestCase.php` calls `withoutVite()` so tests don't need a front-end build.
- Tests wipe their own database (RefreshDatabase), set to `uphsl_credential_tracker_testing` in
  `phpunit.xml`; `tests/TestCase.php` refuses to run unless the database name ends in `_testing`.
- Mail: every environment sends through Gmail SMTP once `MAIL_USERNAME`/`MAIL_PASSWORD` are set,
  and writes to `storage/logs/laravel.log` otherwise. Locally, `Mail::alwaysTo()` redirects every
  email to `MAIL_REDIRECT_TO` (default: `MAIL_USERNAME`). `App\Listeners\DropUndeliverableRecipients`
  drops recipients on reserved example domains, which all demo accounts use.
- The first registrar comes from `INITIAL_REGISTRAR_EMAIL`/`INITIAL_REGISTRAR_PASSWORD` in every
  environment; `RegistrarSeeder` creates it only when missing and never changes it afterwards. Receipts use `config('uphsl.receipts_disk')` (`local` or
  Cloudflare `r2`, foldered per environment); use `RequestWorkflow::proofDisk()`, never a disk name.
  `php artisan app:test-mail <email>` and `app:test-storage` check the settings of the environment
  they run in. Server setup: section 13 of `docs/deployment/01-deploy-on-aws-lightsail.md`.
- Notifications are queued (`QUEUE_CONNECTION=database`); run `composer dev` or `php artisan queue:work`.
- `php artisan db:seed --class=DemoSeeder` creates demo accounts (`@example.com`, password `password`) and requests
  in every status; it runs only in local and staging.
- Request queries for the registrar list and CSV export share `App\Queries\RegistrarRequestFilter`;
  office queues use `App\Queries\StepQueue`.
