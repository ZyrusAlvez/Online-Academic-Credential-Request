# Credentials & Testing

> **Local and staging only.** Every account below uses the password `password` and exists only
> in the **local** and **staging** environments. Production has no demo data; the demo seeder and
> `app:reset-demo` refuse to run there.

## 1. Environments

There are exactly three environments. Any other `APP_ENV` value stops the app at startup.

| | Local | Staging | Production |
|---|---|---|---|
| Where | Your computer: `composer dev` → http://localhost:8000 | The server's staging address | The server's production address |
| Database | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Receipt files | `local/` folder (R2, or `storage/app/private/local`) | `staging/` folder | `production/` folder |
| Data | Base data + demo accounts and requests | Base data + demo accounts and requests | Base data + one registrar account; real data only |
| Email | Sent through Gmail, all redirected to your own inbox | Sent through Gmail | Sent through Gmail |

Base data = the 15 departments, the 7 credential types with their workflows, and the registrar account.

To reload the demo data (local or staging): `php artisan app:reset-demo`.

Automated tests use their own database, `uphsl_credential_tracker_testing`, so running them never
touches your local or staging data.

Open the local or staging address and log in with an **email and password** below.

## 2. Demo accounts

### Registrar (admin)

The registrar is **your own account** from `.env` (`INITIAL_REGISTRAR_EMAIL` /
`INITIAL_REGISTRAR_PASSWORD`), the same way production gets its first registrar. The demo
requests are approved and released by this account.

### Students

Each student already has requests in different states, so every screen has data to show.

| Name | Email | Password | Requests to look at |
|---|---|---|---|
| Juan S. Dela Cruz | `student@example.com` | `password` | TOR awaiting payment; TOR in progress at College of Computer Studies |
| Maria Reyes | `maria@example.com` | `password` | TOR with receipt under review; Good Moral at Student Affairs; Certificate of Enrollment released |
| Jose L. Garcia | `jose@example.com` | `password` | TOR on hold at the Library; Certificate of Grades ready for release |
| Ana Mendoza | `ana@example.com` | `password` | Certificate of Enrollment under review; TOR **overdue** at the Cashier; Good Moral cancelled |

### Department staff

One account per office: `<office code in lower case>@example.com`, password `password`.

All demo accounts use `example.com`, a domain reserved for examples. The app never sends email
to it, so demo data can't bounce or use up the Gmail sending limit.

| Office | Code | Email |
|---|---|---|
| College of Arts and Sciences | CAS | `cas@uphsl.edu.ph` |
| College of Business and Accountancy | CBA | `cba@uphsl.edu.ph` |
| College of Computer Studies | CCS | `ccs@uphsl.edu.ph` |
| College of Criminology | CRIM | `crim@uphsl.edu.ph` |
| College of Education | COED | `coed@uphsl.edu.ph` |
| College of Engineering | COE | `coe@uphsl.edu.ph` |
| College of International Hospitality Management | CIHM | `cihm@uphsl.edu.ph` |
| College of Maritime Education | CME | `cme@uphsl.edu.ph` |
| College of Law | LAW | `law@uphsl.edu.ph` |
| Graduate School | GS | `gs@uphsl.edu.ph` |
| Architecture and Aviation | CAA | `caa@uphsl.edu.ph` |
| Library | LIB | `lib@uphsl.edu.ph` |
| Cashier / Finance Office | FIN | `fin@uphsl.edu.ph` |
| Student Affairs / Student Personnel Services | SAS | `sas@uphsl.edu.ph` |
| Office of the Registrar | REG | `reg@uphsl.edu.ph` |

Registrar accounts can also sign the Office of the Registrar (REG) step.

### New accounts

- **Students** register themselves at `/register` with any email address. They must click the
  verification link before they can submit a request. Locally, every email arrives in your own
  inbox (or in `storage/logs/laravel.log` while Gmail isn't configured).
- **Staff** accounts are created by the registrar under **Administration → Accounts**. They
  are verified automatically.

## 3. Where the real secrets live

| Secret | Location | Notes |
|---|---|---|
| Database user/password | `.env` (`DB_USERNAME`, `DB_PASSWORD`) | `.env` is git-ignored; never copy its values into docs or commits |
| App key | `.env` (`APP_KEY`) | `php artisan key:generate` |
| First registrar | Each `.env` (`INITIAL_REGISTRAR_EMAIL`, `INITIAL_REGISTRAR_PASSWORD`) | Created by the seeder only when no account with that email exists; changing these later does not change the account. Seeding and `app:reset-demo` refuse to run without them |
| Gmail (email) | Each `.env` (`MAIL_USERNAME`, `MAIL_PASSWORD` = Google App Password) | Without them, emails are written to the log. Locally every email goes to your own inbox. Setup: [Lightsail guide §13.1](deployment/01-deploy-on-aws-lightsail.md#131-email-both-environments) |
| Cloudflare R2 (receipts) | `.env` (`RECEIPTS_DISK=r2`, `R2_*`) | One private bucket, one folder per environment (`staging/`, `production/`). Setup: [Lightsail guide §13.2](deployment/01-deploy-on-aws-lightsail.md#132-receipts-cloudflare-r2) |

Each deployment has its own small `.env` (git-ignored); its committed template is `.env.example`.
Production commands are run on the server, in the production folder.

## 4. Automated tests

```bash
composer qa                 # code style (Pint) + static analysis (PHPStan) + all tests
php artisan test            # tests only
php artisan test --filter=REQ-05-04      # one acceptance criterion
php artisan test tests/Feature/Requests  # one area
```

- Tests run against the **staging** database `uphsl_credential_tracker_staging`. **Each run wipes
  it**, then the demo data is reloaded automatically when the tests finish (pass or fail). Anything
  you entered by hand in staging is lost, so don't run the tests while preparing a staging demo.
- Production is never touched: the tests stop before doing anything if their database name does
  not end in `_staging`.
- Reload the staging demo data yourself at any time with `php artisan app:reset-demo`.
- Each test name starts with the acceptance criterion it proves (e.g. `REQ-04-11 accepting a
  payment snapshots the workflow…`), so every requirement in `docs/specs/` can be traced to a test.
- Uploads use a fake disk and mail/notifications are faked where asserted, so no real files or
  emails are produced.

## 5. Manual end-to-end test

Run this in **staging** (`composer dev`). Use three browser profiles (or one normal window plus private windows) to stay logged in as
several roles at once.

| # | As | Do | Expect |
|---|---|---|---|
| 1 | Student (new account) | Register with a real address, open the verification email (locally: in your own inbox) | Lands on **My Requests** |
| 2 | Student | **New Request** → Transcript of Records, college *College of Computer Studies* | Status *Awaiting payment*, **Pay online** button opens the UPHSL payment page |
| 3 | Student | Upload any JPG/PNG/PDF receipt | Status *Payment under review* |
| 4 | Registrar | **Requests** → open it → **Reject payment** with a remark | Student sees the remark and can upload again |
| 5 | Registrar | After a new upload → **Accept payment & route** | Request goes to the Library; `lib@` gets a notification |
| 6 | `lib@example.com` | **Put on hold** with a reason, then **Sign** | Student's tracking shows *On hold at Library* then *Signed by Library* (office only, no staff name) |
| 7 | `fin@`, `ccs@` | Sign in turn | `cba@` never sees this request (only the student's college signs) |
| 8 | Registrar | Sign the Office of the Registrar step → **Mark as released** | Student sees *Ready for release*, then *Released* |
| 9 | Registrar | **Credential Types** → edit a workflow (add/move/remove a step), set a fee | New requests use the new order; requests already in progress keep theirs |
| 10 | Registrar | **Dashboard**, **Requests → Overdue**, **Export CSV** | Ana's TOR shows as overdue; CSV downloads |

To start over: `php artisan app:reset-demo`.
