# UPHSL Credential Tracker

**Online Academic Credential Request, Tracking & Monitoring** for the Office of the Registrar,
University of Perpetual Help System.

## What the system is about

Requesting a credential such as a Transcript of Records means the Registrar's Office must check
the requester's records and requirements, verify the payment, and then pass a paper document
through several offices (the Dean, VP Finance, the Chancellor, the President) before it can be
released. Until now the requester could not tell where that document was or why it was taking
long, and the Registrar could not measure which office caused delays.

This system is a **digital ledger** of that process, following the client's *Proposed Workflow
and Process Flow* (`docs/`). It records every request, the evaluation of its requirements, the
payment verification, every receipt, release and return between offices, and the release to the
owner or an authorized representative. The documents themselves are never sent digitally; the
only uploads are the requester's valid ID, a representative's SPA and IDs, and the proof of
payment.

### Who uses it

| Role | What they do |
|---|---|
| **Requester** (students, graduates, former students) | Registers with a valid ID, requests credentials, complies with missing requirements, pays and uploads the proof, declares a representative, follows the request on a progress tracker, and is notified at each key step |
| **Office staff** (Dean of each college, VP Finance, Chancellor, President) | Accepts receipt of documents sent to their office, approves and releases them to the next office or returns them for correction, and records delay reasons |
| **Registrar** (Office of the Registrar) | Verifies identities, evaluates requests and records compliance, verifies payments and accepts requests, prepares and finally checks every credential, releases it, and manages the credential master list, offices, working calendar and accounts |

### How a request moves

1. The requester submits a request. It is **received for evaluation**, not yet accepted.
2. The registrar evaluates it: requirements complete, incomplete (the requester complies in
   person), needs clarification, or not eligible.
3. The requester pays; the registrar verifies the payment against the official record and
   **accepts** the request. The processing period (in working days) starts here.
4. The document travels the credential's route. Each office accepts receipt, then releases it
   to the next office or returns it for correction. Every visit and its duration are recorded.
5. The Registrar's final quality check makes it ready for release; it is released to the owner
   or a verified representative, and the release is recorded.

Fees, routes, processing periods, requirements and the working calendar are set by the registrar
and can be changed at any time.

## Environments

There are exactly **three** environments, chosen by `APP_ENV` in each deployment's own `.env`.
Any other value stops the app at startup.

| | Local | Staging | Production |
|---|---|---|---|
| Purpose | Development on your own computer | Testing changes and demos on the server | Real use |
| Runs | `composer dev` → http://localhost:8000 | Server folder `/var/www/uphsl-staging` | Server folder `/var/www/uphsl-production` |
| Database | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Data | Base data plus demo accounts and sample requests | Base data plus demo accounts and sample requests | Base data and real data only |
| Email | Sent through Gmail, all redirected to your own inbox | Sent through Gmail | Sent through Gmail |
| Receipts | `local/` folder | `staging/` folder | `production/` folder |
| Error details (debug mode) | Shown | Shown | Never shown |

Base data means the 15 offices, the 17 credential types of the master list with their routes,
the 2026–2027 holiday calendar, and the first registrar account.

The `.env` file is small: the environment name, the address, the database login, and the
secrets for email and receipt storage (see `.env.example`). Everything that follows from the
environment, such as the database name and debug mode, is set automatically in `config/`.

Staging works exactly like production, including email and the first registrar account. The only
differences are its separate database, the demo data, and visible error details. Without Gmail
settings, any environment writes its emails to `storage/logs/laravel.log` instead.

### Branches and deploying

Code moves from a feature branch → `staging` → `production` (the default branch): branches are
merged into `staging` directly, and `staging` reaches `production` through a pull request. A push
to `staging` deploys the staging server automatically; merging the pull request into `production`
deploys the production server. Both deploys run the tests first. Production only accepts code that staging is already running. The scripts in `scripts/` deploy
by hand if GitHub Actions is unavailable. Details:
[docs/deployment/02-environments.md](docs/deployment/02-environments.md).

### Rules that keep production safe

- Automated tests run on their own database, `uphsl_credential_tracker_testing`, and refuse to
  start on any database whose name does not end in `_testing`.
- Demo data and `php artisan app:reset-demo` (reload the demo data) work only in local and staging.
- Each environment has its own database and its own receipts folder, so nothing done in
  local or staging can change production.

## More documentation

- [Credentials and testing](docs/credentials.md): demo accounts and test steps
- [Deploying on AWS Lightsail](docs/deployment/01-deploy-on-aws-lightsail.md): step-by-step server setup for staging and production
- [Environments, branches and deploying](docs/deployment/02-environments.md): how local, staging and production differ, and one-click deploys
- [Data dictionary](docs/data-dictionary.md), [design documents](docs/des/), [specifications](docs/specs/00-overview.md)
