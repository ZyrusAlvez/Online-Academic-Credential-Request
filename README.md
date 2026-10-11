# UPHSL Credential Tracker

**Online Academic Credential Request, Tracking & Monitoring** for the Office of the Registrar,
University of Perpetual Help System.

Students request credentials (Transcript of Records, certifications, …) online and follow the
paper document from office to office like a parcel. Departments see what is waiting for their
signature, and the registrar controls the flow. The system is a **digital ledger**: credential
documents are never transferred digitally. The only upload is the student's proof of payment
for paid credentials, paid on the UPHSL online payment portal.

## Features

| Role | Can |
|---|---|
| **Student** | Register (name, email, password), verify email, request credentials, pay online and upload the receipt, see *where the document is now* and its full tracking history, get in-app + email updates, cancel before routing |
| **Department staff** | See a signature queue for their office, sign, put on hold with a reason, lift holds, see their history |
| **Registrar** (admin) | Review requests and receipts, approve & route, reject payments, correct the college, skip steps, release, cancel; manage departments, credential types, **per-credential signing workflows** and staff accounts; monitor with a dashboard (overdue, bottlenecks, turnaround) and CSV export |

Key rules:

- Workflows are **strictly sequential** and configured per credential type by the registrar.
- A **Student's College** step resolves to the college chosen on the request, so only that
  college's Dean signs.
- When a request is routed, it gets its own copy of the workflow. Later workflow edits never
  disturb requests already in progress.
- Every change is appended to an **immutable ledger** (`request_events`).

## Stack

Laravel 12 · PHP 8.4+ · MySQL 8 · Livewire 4 + Flux · Tailwind CSS 4 · Pest · Larastan (level 6) · Pint

## Getting started

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# set DB_DATABASE / DB_USERNAME / DB_PASSWORD (MySQL), then:
php artisan migrate --seed                  # departments, credential types, registrar account
php artisan db:seed --class=DemoSeeder      # optional: demo accounts and sample requests
composer dev                                # server, queue worker, logs and Vite
```

Open http://localhost:8000. Emails go to `storage/logs/laravel.log` (`MAIL_MAILER=log`) until SMTP is configured.
Notifications are queued, so keep the queue worker running (`composer dev` does).

Tests use a separate MySQL database, `thesis_testing` (see `phpunit.xml`).

### Demo accounts (DemoSeeder; password `password`)

| Role | Email |
|---|---|
| Registrar | `registrar@uphsl.edu.ph` |
| Student | `student@example.com` (also `maria@`, `jose@`, `ana@example.com`) |
| Department staff | `<office code>@uphsl.edu.ph`, e.g. `lib@`, `fin@`, `sas@`, `ccs@`, `cba@`, `reg@uphsl.edu.ph` |

In production, set `SEED_REGISTRAR_EMAIL` / `SEED_REGISTRAR_PASSWORD` before `php artisan db:seed`.
The demo seeder refuses to run in production.

## Quality gate

```bash
composer qa      # pint --test · phpstan · pest
composer fix     # apply code style
```

## Architecture

```
app/
  Enums/                 Role, RequestStatus, StepStatus, PaymentStatus, RequestEventType, …
  Services/RequestWorkflow.php   the ONLY place request/step statuses change
                                 (row locks, transition guards, ledger, after-commit event)
  Listeners/SendRequestNotifications.php   who hears about each ledger event
  Notifications/RequestActivity.php        database + (students, key events) mail, queued
  Policies/              who may view/act; cross-user access answers 404
  Queries/               StepQueue (office queues), RegistrarRequestFilter (list + CSV)
  Livewire/{Student,Department,Registrar}/  screens per role
specs/                   spec-driven development: requirements (EARS, REQ-xx-yy), design, tasks
tests/Feature/           Pest tests named after the acceptance criteria they verify
```

Request lifecycle:

```
awaiting_payment → payment_review ─┐            (paid credentials)
registrar_review ──────────────────┴→ in_progress → ready_for_release → released
                                          step: pending ⇄ on_hold → signed | skipped
any open status → cancelled
```

## Specifications

| Spec | Topic |
|---|---|
| [00](specs/00-overview.md) | Product overview, roles, lifecycle |
| [01](specs/01-auth) | Authentication & roles |
| [02](specs/02-departments-staff) | Departments & staff accounts |
| [03](specs/03-credential-types-workflows) | Credential types & workflow builder |
| [04](specs/04-request-submission-payment) | Request submission, payment & registrar review |
| [05](specs/05-department-signing-release) | Department signing, hold, skip & release |
| [06](specs/06-tracking-notifications) | Tracking timeline & notifications |
| [07](specs/07-registrar-console) | Registrar console & monitoring |
