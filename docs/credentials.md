# Credentials & Testing

> **Development and demo use only.** Every account below uses the password `password` and is
> created by the seeders. Never run the demo seeder on a production server; it refuses to run
> when `APP_ENV=production`.

## 1. Loading the demo data

```bash
php artisan migrate:fresh --seed            # departments, credential types, registrar account
php artisan db:seed --class=DemoSeeder      # demo students, one staff account per office, sample requests
composer dev                                # app + queue worker (needed for notifications) + Vite
```

Open http://localhost:8000 and log in with an **email and password** below.

## 2. Demo accounts

### Registrar (admin)

| Name | Email | Password |
|---|---|---|
| Office of the Registrar | `registrar@uphsl.edu.ph` | `password` |

### Students

Each student already has requests in different states, so every screen has data to show.

| Name | Email | Password | Requests to look at |
|---|---|---|---|
| Juan S. Dela Cruz | `student@example.com` | `password` | TOR awaiting payment; TOR in progress at College of Computer Studies |
| Maria Reyes | `maria@example.com` | `password` | TOR with receipt under review; Good Moral at Student Affairs; Certificate of Enrollment released |
| Jose L. Garcia | `jose@example.com` | `password` | TOR on hold at the Library; Certificate of Grades ready for release |
| Ana Mendoza | `ana@example.com` | `password` | Certificate of Enrollment under review; TOR **overdue** at the Cashier; Good Moral cancelled |

### Department staff

One account per office: `<office code in lower case>@uphsl.edu.ph`, password `password`.

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
  verification link before they can submit a request. Locally, the email is written to
  `storage/logs/laravel.log`.
- **Staff** accounts are created by the registrar under **Administration → Accounts**. They
  are verified automatically.

## 3. Where the real secrets live

| Secret | Location | Notes |
|---|---|---|
| Database user/password | `.env` (`DB_USERNAME`, `DB_PASSWORD`) | `.env` is git-ignored; never copy its values into docs or commits |
| App key | `.env` (`APP_KEY`) | `php artisan key:generate` |
| First registrar on a real server | `.env` (`SEED_REGISTRAR_EMAIL`, `SEED_REGISTRAR_PASSWORD`) | Seeding refuses to run in production without a password |
| Mail (SMTP) | `.env` (`MAIL_*`) | Defaults to the `log` mailer |

Local databases: `uphsl_credential_tracker` (app) and `uphsl_credential_tracker_test` (tests only; wiped on every test run).

## 4. Automated tests

```bash
composer qa                 # code style (Pint) + static analysis (PHPStan) + all tests
php artisan test            # tests only
php artisan test --filter=REQ-05-04      # one acceptance criterion
php artisan test tests/Feature/Requests  # one area
```

- Tests run against the MySQL database `uphsl_credential_tracker_test` (configured in `phpunit.xml`), never `uphsl_credential_tracker`.
- Each test name starts with the acceptance criterion it proves (e.g. `REQ-04-11 accepting a
  payment snapshots the workflow…`), so every requirement in `docs/specs/` can be traced to a test.
- Uploads use a fake disk and mail/notifications are faked where asserted, so no real files or
  emails are produced.

## 5. Manual end-to-end test

Use three browser profiles (or one normal window plus private windows) to stay logged in as
several roles at once.

| # | As | Do | Expect |
|---|---|---|---|
| 1 | Student (new account) | Register, open the verification link from `storage/logs/laravel.log` | Lands on **My Requests** |
| 2 | Student | **New Request** → Transcript of Records, college *College of Computer Studies* | Status *Awaiting payment*, **Pay online** button opens the UPHSL payment page |
| 3 | Student | Upload any JPG/PNG/PDF receipt | Status *Payment under review* |
| 4 | Registrar | **Requests** → open it → **Reject payment** with a remark | Student sees the remark and can upload again |
| 5 | Registrar | After a new upload → **Accept payment & route** | Request goes to the Library; `lib@` gets a notification |
| 6 | `lib@uphsl.edu.ph` | **Put on hold** with a reason, then **Sign** | Student's tracking shows *On hold at Library* then *Signed by Library* (office only, no staff name) |
| 7 | `fin@`, `ccs@` | Sign in turn | `cba@` never sees this request (only the student's college signs) |
| 8 | Registrar | Sign the Office of the Registrar step → **Mark as released** | Student sees *Ready for release*, then *Released* |
| 9 | Registrar | **Credential Types** → edit a workflow (add/move/remove a step), set a fee | New requests use the new order; requests already in progress keep theirs |
| 10 | Registrar | **Dashboard**, **Requests → Overdue**, **Export CSV** | Ana's TOR shows as overdue; CSV downloads |

To start over: `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder`.
