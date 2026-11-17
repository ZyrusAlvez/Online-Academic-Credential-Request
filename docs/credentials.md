# Credentials & Testing

> **Local and staging only.** Every demo account below uses the password `@Test123` (it meets the
> app's password rules) and exists only
> in the **local** and **staging** environments. The admin is the one exception: it comes from
> `.env`. Production has no demo data; the demo seeder and
> `app:reset-demo` refuse to run there.

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

To reload the demo data (local or staging): `php artisan app:reset-demo`. It wipes the database,
then loads the demo accounts of section 2, their sample requests (with a saved valid ID for each
requester) and about six months of generated activity (spec 31): about 550 requests in every
status and at every office, from about 60 generated requester accounts. It takes a minute or two.
`DEMO_REQUESTERS` and `DEMO_APPLICATIONS` in `.env` change the amount (defaults 60 and 400).

Automated tests use their own database, `uphsl_credential_tracker_testing`, so running them never
touches your local or staging data.

Open the local or staging address and log in with an **email and password** below.

## 2. Demo accounts

### Registrar

| Email | Password | Sees |
|---|---|---|
| `registrar@example.com` | `@Test123` | Initial Evaluation, Payment Validation, In Progress, Final Quality Check, Release and Records |

The sample requests are evaluated, accepted, prepared, checked and released by this account. The
generated requests share the evaluations with `registrar2@example.com` and
`registrar3@example.com` (same password), so the evaluator report has several people.

### Admin

There is no demo admin. The only admin is the first admin from that environment's `.env`, the
same way production gets its own.

| Email | Password | Sees |
|---|---|---|
| `INITIAL_ADMIN_EMAIL` | `INITIAL_ADMIN_PASSWORD` | Dashboard, Records, Reports, and Administration (Credential Types, Departments, Programs, Purposes, Accounts, Working Calendar, Logs) |

It is the only account every environment seeds (production included). The admin creates the
registrar and department staff accounts under **Administration → Accounts** (spec 18).

### Requesters

The last column lists their sample requests.

| Name | Email | Password | College and program | Sample requests |
|---|---|---|---|---|
| Juan S. Dela Cruz | `student@example.com` | `@Test123` | CCS · Bachelor of Science in Computer Science, Specialization in Data Science | TOR just submitted; payment to verify; TOR **returned for correction** by VP Finance; Certification of Enrollment released; diploma received by the Registrar |
| Maria Reyes | `maria@example.com` | `@Test123` | CBA · Bachelor of Science in Accountancy | Certification under evaluation; Course Description awaiting payment; TOR at the Dean of CBA; Certificate of Grades ready for release to her verified representative; TOR **overdue** at the Chancellor |
| Jose L. Garcia | `jose@example.com` | `@Test123` | CEA · Bachelor of Science in Civil Engineering | TOR pending compliance; Certification of Graduation just accepted; Semestral Report awaiting payment; Certificate of Transfer at the final check; TOR released late; one cancelled |
| Ana Mendoza | `ana@example.com` | `@Test123` | CTE · Bachelor of Secondary Education | Certificate of Grades resubmitted after a blurred ID; Authentication declined; an application of two credentials; a walk-in TOR |

**Generated requesters.** About 60 more requester accounts exist only to fill the dashboard,
reports and queues, e.g. `maria.santos.07@example.com` (same password). They are not listed here;
**Administration → Accounts** shows them all.

### Department staff

One account per office: `<office code in lower case>@example.com`, password `@Test123`.

All demo accounts use `example.com`, a domain reserved for examples. The app never sends email
to it, so demo data can't bounce or use up the Gmail sending limit.

| Office | Code | Email |
|---|---|---|
| College of Arts and Sciences | CAS | `cas@example.com` |
| School of Aviation | SOA | `soa@example.com` |
| Senior High School | SHS | `shs@example.com` |
| College of Business and Accountancy | CBA | `cba@example.com` |
| College of Computer Studies | CCS | `ccs@example.com` |
| College of Criminology | COC | `coc@example.com` |
| College of Teacher Education | CTE | `cte@example.com` |
| College of Engineering and Architecture | CEA | `cea@example.com` |
| Graduate School | GS | `gs@example.com` |
| College of Nutrition, Hospitality and Tourism Management | CNHTM | `cnhtm@example.com` |
| College of Maritime Education | CME | `cme@example.com` |
| Office of the VP for Finance | VPF | `vpf@example.com` |
| Office of the Chancellor | CHAN | `chan@example.com` |
| Office of the President | PRES | `pres@example.com` |
| Office of the Registrar | REG | `reg@example.com` |

Production has no demo accounts: the admin creates the college staff accounts under
**Administration → Accounts**.

### Programs

| College | Programs |
|---|---|
| CAS | Bachelor of Arts in Communication · Bachelor of Arts in Political Science · Bachelor of Arts/Science in Psychology |
| SOA | Bachelor of Science in Aircraft Maintenance and Technology · Bachelor of Science in Aviation Electronics Technology |
| SHS | Science, Technology, Engineering and Mathematics (STEM) |
| CBA | Bachelor of Science in Accountancy · Bachelor of Science in Business Administration · Bachelor of Science in Management Accounting · Bachelor of Science in Real Estate Management |
| CCS | Bachelor of Information Technology in Cybersecurity and Forensics · Bachelor of Science in Computer Science, Specialization in Data Science · Bachelor of Science in Entertainment and Multimedia Computing · Bachelor of Science in Information Technology, Specialization in Game Development |
| COC | Bachelor of Science in Criminology |
| CTE | Bachelor of Early Childhood Education · Bachelor of Elementary Education · Bachelor of Library and Information Science · Bachelor of Physical Education · Bachelor of Secondary Education · Bachelor of Special Needs Education |
| CEA | Bachelor of Science in Architecture · Bachelor of Science in Civil Engineering · Bachelor of Science in Computer Engineering · Bachelor of Science in Electrical Engineering · Bachelor of Science in Electronics Engineering · Bachelor of Science in Industrial Engineering · Bachelor of Science in Mechanical Engineering |
| GS | Graduate School |
| CNHTM | Bachelor of Science in Hospitality Management · Bachelor of Science in Nutrition & Dietetics · Bachelor of Science in Tourism Management |
| CME | Bachelor of Science in Marine Engineering · Bachelor of Science in Marine Transportation |

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
| First admin | Each `.env` (`INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD`) | The only seeded account. Created by the seeder only when no account with that email exists; changing these later does not change the account. Seeding, `app:reset` and `app:reset-demo` refuse to run without them. Deploys never seed, so on a running server add it once with `php artisan db:seed --class=AdminSeeder --force` |
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
