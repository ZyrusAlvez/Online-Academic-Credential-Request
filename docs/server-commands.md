# Environments, Commands and Testing

Demo accounts: [credentials.md](credentials.md).

## 1. Environments

There are exactly three environments. Any other `APP_ENV` value stops the app at startup.

| | Local | Staging | Production |
|---|---|---|---|
| Where | Your computer: `composer dev` → http://localhost:8000 | The server's staging address | The server's production address |
| Database | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Receipt files | `local/` folder (R2, or `storage/app/private/local`) | `staging/` folder | `production/` folder |
| Data | Base data + demo accounts | Base data + demo accounts | Base data + the first admin account; real data only |
| Email | Sent through Gmail, all redirected to your own inbox | Sent through Gmail | Sent through Gmail |

Base data = the 15 offices (11 colleges and 4 offices), the 34 programs, the 17 credential types
with their routes, the request purposes, the holiday calendar, and the first admin account (from `.env`).

Demo accounts exist only in local and staging. Production has no demo data; the demo seeder and
`app:reset-demo` refuse to run there.

## 2. Demo data

To reload the demo data (local or staging): `php artisan app:reset-demo`. It wipes the database,
then loads the demo accounts, their sample requests (with a saved valid ID for each requester)
and about six months of generated activity (spec 31): about 550 requests in every status and at
every office, from about 60 generated requester accounts (e.g. `maria.santos.07@example.com`) and
a third registrar, `registrar3@example.com`. It takes a minute or two. `DEMO_REQUESTERS` and
`DEMO_APPLICATIONS` in `.env` change the amount (defaults 60 and 400).

The data is dated around the moment it is loaded. To load it ahead of a presentation, date it to
the presentation instead: `php artisan app:reset-demo --as-of="2026-11-20 08:00"` (or `DEMO_AS_OF`
in `.env`). Pick a moment before the presentation starts: nothing is dated after it, and around
that day some credentials are overdue by 1, 3 and 5 working days, some are due that day and on
the next two working days, and new applications arrive that morning (spec 31).

All demo accounts use `example.com`, a domain reserved for examples. The app never sends email
to it, so demo data can't bounce or use up the Gmail sending limit.

## 3. New accounts

- **Requesters** register themselves at `/register` with any email address, then fill in **My
  Profile** and upload a valid ID. They must verify their email before they can submit a request.
  Locally, every email arrives in your own inbox (or in `storage/logs/laravel.log` while Gmail
  isn't configured).
- **Staff** accounts (department staff, registrars and admins) are created by an admin under
  **Administration → Accounts**. They are verified automatically.

## 4. Where the real secrets live

| Secret | Location | Notes |
|---|---|---|
| Database user/password | `.env` (`DB_USERNAME`, `DB_PASSWORD`) | `.env` is git-ignored; never copy its values into docs or commits |
| App key | `.env` (`APP_KEY`) | `php artisan key:generate` |
| First admin | Each `.env` (`INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD`) | The only seeded account. Created by the seeder only when no account with that email exists; changing these later does not change the account. Seeding, `app:reset` and `app:reset-demo` refuse to run without them. Deploys never seed, so on a running server add it once with `php artisan db:seed --class=AdminSeeder --force` |
| Gmail (email) | Each `.env` (`MAIL_USERNAME`, `MAIL_PASSWORD` = Google App Password) | Without them, emails are written to the log. Locally every email goes to your own inbox. Setup: [Lightsail guide §13.1](deployment/01-deploy-on-aws-lightsail.md#131-email-both-environments) |
| Cloudflare R2 (receipts) | `.env` (`RECEIPTS_DISK=r2`, `R2_*`) | One private bucket, one folder per environment (`staging/`, `production/`). Setup: [Lightsail guide §13.2](deployment/01-deploy-on-aws-lightsail.md#132-receipts-cloudflare-r2) |

Each deployment has its own small `.env` (git-ignored); its committed template is `.env.example`.
Production commands are run on the server, in the production folder.

## 5. Automated tests

```bash
composer qa                 # code style (Pint) + static analysis (PHPStan) + all tests
php artisan test            # tests only
php artisan test --filter=REQ-05-04      # one acceptance criterion
php artisan test tests/Feature/Requests  # one area
```

- Tests run against their own database, `uphsl_credential_tracker_testing`, which **each run
  wipes**; they refuse to run on any database whose name does not end in `_testing`, so local,
  staging and production data are never touched.
- Each test name starts with the acceptance criterion it proves (e.g. `REQ-04-14 acceptance starts
  the clock…`), so every requirement in `docs/specs/` can be traced to a test.
- Uploads use a fake disk and mail/notifications are faked where asserted, so no real files or
  emails are produced.

## 6. Manual end-to-end test

Run this in **local or staging**. Use several browser profiles (or private windows) to stay
logged in as several roles at once.

| # | As | Do | Expect |
|---|---|---|---|
| 1 | Requester (new account) | Register with a real address and consent, verify the email, fill in **My Profile** and upload an ID | **New Request** opens only after the profile and ID are done |
| 2 | Registrar | **Identity Checks** → verify the new ID | The requester is emailed |
| 3 | Requester | **New Request** → Transcript of Records | Sees requirements, ₱530.00 due, offices, the incomplete-requirements notice; status *Submitted* |
| 4 | Registrar | **Requests** → open it → **Start evaluation** → **Requirements incomplete** (tick the PSA birth certificate) | Requester sees the deficiency and the compliance notice |
| 5 | Registrar | **Record compliance** → **Requirements complete** | Requester gets payment instructions |
| 6 | Requester | Upload a proof with reference, amount and date | *Payment verification* |
| 7 | Registrar | **Verify payment & accept** with an OR number | *Accepted for processing*; expected release date shown to the requester |
| 8 | Registrar, then `ccs@`, `vpf@` | **Accept receipt** → release to the next office; at VP Finance **Return for correction** to the Dean | Each visit appears in **Office visits**; requester still sees *In progress* |
| 9 | `ccs@`, `vpf@`, `chan@`, Registrar | Correct and send back, receive again, continue; Registrar does the final check | *Ready for release* |
| 10 | Requester, then Registrar | Add a representative; verify them; **Release** to the representative | Released; the release record shows who received it |
| 11 | Registrar | **Dashboard** (period indicators, time per office), **Reports** (each report, filters, CSV), **Requests → Overdue**, **Working Calendar** | Maria's TOR is overdue; the delay analysis shows the Chancellor's recorded reason |

To start over: `php artisan app:reset-demo`.
