# Credentials & Testing

> **Local and staging only.** Every account below uses the password `@Test123` (it meets the
> app's password rules) and exists only
> in the **local** and **staging** environments. Production has no demo data; the demo seeder and
> `app:reset-demo` refuse to run there.

## 1. Environments

There are exactly three environments. Any other `APP_ENV` value stops the app at startup.

| | Local | Staging | Production |
|---|---|---|---|
| Where | Your computer: `composer dev` → http://localhost:8000 | The server's staging address | The server's production address |
| Database | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Receipt files | `local/` folder (R2, or `storage/app/private/local`) | `staging/` folder | `production/` folder |
| Data | Base data + demo accounts and requests | Base data + demo accounts and requests | Base data + the first registrar and admin accounts; real data only |
| Email | Sent through Gmail, all redirected to your own inbox | Sent through Gmail | Sent through Gmail |

Base data = the 15 offices (11 colleges and 4 offices), the 27 programs, the 17 credential types
with their routes, the request purposes, the holiday calendar, and the first registrar and admin
accounts.

To reload the demo data (local or staging): `php artisan app:reset-demo`.

Automated tests use their own database, `uphsl_credential_tracker_testing`, so running them never
touches your local or staging data.

Open the local or staging address and log in with an **email and password** below.

## 2. Demo accounts

### Registrar

| Email | Password | Sees |
|---|---|---|
| `registrar@example.com` | `@Test123` | Initial Evaluation, Payment Validation, In Progress, Final Quality Check, Release and Records |

The demo requests are evaluated, accepted, prepared, checked and released by this account.

### Admin

| Email | Password | Sees |
|---|---|---|
| `admin@example.com` | `@Test123` | Dashboard, Records, Reports, and Administration (Credential Types, Departments, Programs, Purposes, Accounts, Working Calendar, Logs) |

Your own admin account from `.env` (`INITIAL_ADMIN_EMAIL` / `INITIAL_ADMIN_PASSWORD`) also exists,
the same way production gets its first admin. Production has no demo accounts: its first admin is
the only seeded account, and it creates the registrar and department staff accounts (spec 18).

### Requesters

Each requester already has requests in different states, so every screen has data to show.

| Name | Email | Password | College and program | Requests to look at |
|---|---|---|---|---|
| Juan S. Dela Cruz | `student@example.com` | `@Test123` | CCS · Bachelor of Science in Computer Science, Specialization in Data Science | TOR just submitted; payment to verify; TOR **returned for correction** by VP Finance; Certification of Enrollment released; diploma paused for his action |
| Maria Reyes | `maria@example.com` | `@Test123` | CBAA · Bachelor of Science in Accountancy | Certification under evaluation; Course Description awaiting payment; TOR at the Dean of CBAA; Copy of Grades ready for release to her verified representative; TOR **overdue** at the Chancellor |
| Jose L. Garcia | `jose@example.com` | `@Test123` | CEA · Bachelor of Science in Civil Engineering | TOR pending compliance; Certification of Graduation just accepted; Transfer Certificate at the final check; TOR released late; one cancelled |
| Ana Mendoza | `ana@example.com` | `@Test123` | CTE · Bachelor of Secondary Education | ID still **awaiting verification** (Identity Checks); Copy of Grades waiting for her clarification; Authentication declined |

Juan, Maria and Jose are verified, so their My Profile is read-only. Ana's is read-only while her ID
waits; reject it on Identity Checks to see the profile open up again.

### Department staff

One account per office: `<office code in lower case>@example.com`, password `@Test123`.

All demo accounts use `example.com`, a domain reserved for examples. The app never sends email
to it, so demo data can't bounce or use up the Gmail sending limit.

| Office | Code | Email |
|---|---|---|
| College of Arts and Sciences | CAS | `cas@example.com` |
| School of Aviation | SOA | `soa@example.com` |
| Basic Education Department | BED | `bed@example.com` |
| College of Business Administration and Accountancy | CBAA | `cbaa@example.com` |
| College of Computer Studies | CCS | `ccs@example.com` |
| College of Criminal Justice Education | CCJE | `ccje@example.com` |
| College of Teacher Education | CTE | `cte@example.com` |
| College of Engineering and Architecture | CEA | `cea@example.com` |
| Graduate School | GS | `gs@example.com` |
| College of Nutrition, Hospitality and Tourism Management | CNHTM | `cnhtm@example.com` |
| College of Maritime Education | CME | `cme@example.com` |
| Office of the VP for Finance | VPF | `vpf@example.com` |
| Office of the Chancellor | CHAN | `chan@example.com` |
| Office of the President | PRES | `pres@example.com` |
| Office of the Registrar | REG | `reg@example.com` |

The college list changed on 2026-11-06. The old addresses (`cba@`, `crim@`, `coed@`, `coe@`,
`cihm@`, `caa@`, `law@`) no longer exist. On staging the deploy moved each demo account to its
college's new address, keeping its history, and removed `law@`. Any other college staff account,
such as the Basic Education starter account, was deleted, or deactivated if it had already acted.
Production gets the same clean-up and no new accounts: the admin creates the college staff
accounts under **Administration → Accounts**.

### Programs

| College | Programs |
|---|---|
| CAS | Bachelor of Arts in Communication · Bachelor of Arts in Political Science · Bachelor of Arts/Science in Psychology |
| SOA | Bachelor of Science in Aircraft Maintenance and Technology · Bachelor of Science in Aviation Electronics Technology |
| BED | Basic Education |
| CBAA | Bachelor of Science in Accountancy · Bachelor of Science in Business Administration · Bachelor of Science in Real Estate Management |
| CCS | Bachelor of Information Technology in Cybersecurity and Forensics · Bachelor of Science in Computer Science, Specialization in Data Science · Bachelor of Science in Entertainment and Multimedia Computing |
| CCJE | Bachelor of Science in Criminology |
| CTE | Bachelor of Early Childhood Education · Bachelor of Elementary Education · Bachelor of Library and Information Science · Bachelor of Physical Education · Bachelor of Secondary Education |
| CEA | Bachelor of Science in Architecture · Bachelor of Science in Civil Engineering · Bachelor of Science in Computer Engineering · Bachelor of Science in Industrial Engineering |
| GS | Graduate School |
| CNHTM | Bachelor of Science in Hospitality Management · Bachelor of Science in Nutrition & Dietetics · Bachelor of Science in Tourism Management |
| CME | Bachelor of Science in Marine Transportation |

Registrar accounts also act on the Office of the Registrar's steps (preparation and the final
quality check).

### New accounts

- **Requesters** register themselves at `/register` with any email address, then fill in **My
  Profile** and upload a valid ID. They must click the verification link before they can submit
  a request. Locally, every email arrives in your own
  inbox (or in `storage/logs/laravel.log` while Gmail isn't configured).
- **Staff** accounts (department staff, registrars and admins) are created by an admin under
  **Administration → Accounts**. They are verified automatically.

## 3. Where the real secrets live

| Secret | Location | Notes |
|---|---|---|
| Database user/password | `.env` (`DB_USERNAME`, `DB_PASSWORD`) | `.env` is git-ignored; never copy its values into docs or commits |
| App key | `.env` (`APP_KEY`) | `php artisan key:generate` |
| First admin | Each `.env` (`INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD`) | The only seeded account. Created by the seeder only when no account with that email exists; changing these later does not change the account. Seeding and `app:reset-demo` refuse to run without them. Deploys never seed, so on a running server add it once with `php artisan db:seed --class=AdminSeeder --force` |
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
