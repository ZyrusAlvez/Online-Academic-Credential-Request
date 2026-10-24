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

Base data = the 15 offices, the 17 credential types with their routes, the holiday calendar, and the registrar account.

To reload the demo data (local or staging): `php artisan app:reset-demo`.

Automated tests use their own database, `uphsl_credential_tracker_testing`, so running them never
touches your local or staging data.

Open the local or staging address and log in with an **email and password** below.

## 2. Demo accounts

### Registrar (admin)

The registrar is **your own account** from `.env` (`INITIAL_REGISTRAR_EMAIL` /
`INITIAL_REGISTRAR_PASSWORD`), the same way production gets its first registrar. The demo
requests are evaluated, accepted, prepared, checked and released by this account.

### Requesters

Each requester already has requests in different states, so every screen has data to show.

| Name | Email | Password | Requests to look at |
|---|---|---|---|
| Juan S. Dela Cruz | `student@example.com` | `password` | TOR just submitted; payment to verify; TOR **returned for correction** by VP Finance; Certification of Enrollment released; diploma paused for his action |
| Maria Reyes | `maria@example.com` | `password` | Certification under evaluation; Course Description awaiting payment; TOR at the Dean of CBA; Copy of Grades ready for release to her verified representative; TOR **overdue** at the Chancellor |
| Jose L. Garcia | `jose@example.com` | `password` | TOR pending compliance; Certification of Graduation just accepted; Transfer Certificate at the final check; TOR released late; one cancelled |
| Ana Mendoza | `ana@example.com` | `password` | ID still **awaiting verification** (Identity Checks); Copy of Grades waiting for her clarification; Authentication declined |

### Department staff

One account per office: `<office code in lower case>@example.com`, password `password`.

All demo accounts use `example.com`, a domain reserved for examples. The app never sends email
to it, so demo data can't bounce or use up the Gmail sending limit.

| Office | Code | Email |
|---|---|---|
| College of Arts and Sciences | CAS | `cas@example.com` |
| College of Business and Accountancy | CBA | `cba@example.com` |
| College of Computer Studies | CCS | `ccs@example.com` |
| College of Criminology | CRIM | `crim@example.com` |
| College of Education | COED | `coed@example.com` |
| College of Engineering | COE | `coe@example.com` |
| College of International Hospitality Management | CIHM | `cihm@example.com` |
| College of Maritime Education | CME | `cme@example.com` |
| College of Law | LAW | `law@example.com` |
| Graduate School | GS | `gs@example.com` |
| Architecture and Aviation | CAA | `caa@example.com` |
| Office of the VP for Finance | VPF | `vpf@example.com` |
| Office of the Chancellor | CHAN | `chan@example.com` |
| Office of the President | PRES | `pres@example.com` |
| Office of the Registrar | REG | `reg@example.com` |

Registrar accounts also act on the Office of the Registrar's steps (preparation and the final
quality check).

### New accounts

- **Requesters** register themselves at `/register` with any email address, then fill in **My
  Profile** and upload a valid ID. They must click the verification link before they can submit
  a request. Locally, every email arrives in your own
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

- Tests run against their own database, `uphsl_credential_tracker_testing`, which **each run
  wipes**; they refuse to run on any database whose name does not end in `_testing`, so local,
  staging and production data are never touched.
- Each test name starts with the acceptance criterion it proves (e.g. `REQ-04-14 acceptance starts
  the clock…`), so every requirement in `docs/specs/` can be traced to a test.
- Uploads use a fake disk and mail/notifications are faked where asserted, so no real files or
  emails are produced.

## 5. Manual end-to-end test

Run this in **local or staging**. Use several browser profiles (or private windows) to stay
logged in as several roles at once.

| # | As | Do | Expect |
|---|---|---|---|
| 1 | Requester (new account) | Register with a real address and consent, verify the email, fill in **My Profile** and upload an ID | **New Request** opens only after the profile and ID are done |
| 2 | Registrar | **Identity Checks** → verify the new ID | The requester is emailed |
| 3 | Requester | **New Request** → Transcript of Records, 2 copies | Sees requirements, ₱1,060.00 due, offices, the incomplete-requirements notice; status *Submitted* |
| 4 | Registrar | **Requests** → open it → **Start evaluation** → **Requirements incomplete** (tick the PSA birth certificate) | Requester sees the deficiency and the compliance notice |
| 5 | Registrar | **Record compliance** → **Requirements complete** | Requester gets payment instructions |
| 6 | Requester | Upload a proof with reference, amount and date | *Payment verification* |
| 7 | Registrar | **Verify payment & accept** with an OR number | *Accepted for processing*; expected release date shown to the requester |
| 8 | Registrar, then `ccs@`, `vpf@` | **Accept receipt** → release to the next office; at VP Finance **Return for correction** to the Dean | Each visit appears in **Office visits**; requester still sees *In progress* |
| 9 | `ccs@`, `vpf@`, `chan@`, Registrar | Correct and send back, receive again, continue; Registrar does the final check | *Ready for release* |
| 10 | Requester, then Registrar | Add a representative; verify them; **Release** to the representative | Released; the release record shows who received it |
| 11 | Registrar | **Dashboard**, **Requests → Overdue**, **Export CSV**, **Working Calendar** | Maria's TOR is overdue; CSV downloads |

To start over: `php artisan app:reset-demo`.
