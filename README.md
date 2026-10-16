# UPHSL Credential Tracker

**Online Academic Credential Request, Tracking & Monitoring** for the Office of the Registrar,
University of Perpetual Help System.

## What the system is about

Requesting a credential such as a Transcript of Records means a paper document has to be
signed by several offices before the student can claim it. Until now the student could not
tell where that document was or why it was taking long.

This system is a **digital ledger** of that process. It records every request, every
hand-off between offices and every signature, so anyone involved can see where a document is
right now. The documents themselves are never sent digitally; the only file uploaded is the
student's proof of payment.

### Who uses it

| Role | What they do |
|---|---|
| **Student** | Requests a credential, pays online for paid ones (e.g. TOR) and uploads the receipt, sees where the document is right now and its full history, and is notified of every update |
| **Department** (colleges and offices) | Sees the requests waiting for their signature, signs them, or puts them on hold with a reason (e.g. an unreturned library book) |
| **Registrar** (admin) | Verifies receipts, approves and routes requests, releases finished documents, manages departments and accounts, and decides which offices sign each credential and in what order |

### How a request moves

1. The student submits a request. Paid credentials wait for the uploaded receipt.
2. The registrar reviews it and routes it to the signing offices.
3. The offices sign one after another. Only the student's own college signs, not every college.
4. When the last office signs, the document is ready for release at the Registrar.

Each credential's signing order and fee are set by the registrar and can be changed at any time.

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

Base data means the 15 departments, the 7 credential types with their signing workflows, and
the first registrar account.

The `.env` file is small: the environment name, the address, the database login, and the
secrets for email and receipt storage (see `.env.example`). Everything that follows from the
environment, such as the database name and debug mode, is set automatically in `config/`.

Staging works exactly like production, including email and the first registrar account. The only
differences are its separate database, the demo data, and visible error details. Without Gmail
settings, any environment writes its emails to `storage/logs/laravel.log` instead.

### Branches and deploying

Code moves from a feature branch → `staging` → `production` (the default branch) through pull
requests. The staging server runs the
`staging` branch and the production server runs the `production` branch. Deploy with one click:
`scripts/deploy-staging.sh` / `deploy-production.sh` on Ubuntu, or the `.bat` files on Windows.
Production only accepts code that staging is already running. Details:
[docs/deployment/02-environments.md](docs/deployment/02-environments.md).

### Rules that keep production safe

- Automated tests run on their own database, `uphsl_credential_tracker_testing`, and refuse to
  start on any database whose name does not end in `_testing`.
- Demo data and `php artisan app:reset-demo` (reload the demo data) work only in local and staging.
- Each environment has its own database and its own receipts folder, so nothing done in
  local or staging can change production.

## More documentation

- [Credentials and testing](docs/credentials.md): demo accounts and test steps
- [Email and receipt storage setup](docs/setup-mail-and-storage.md): Gmail and Cloudflare R2
- [Deploying on AWS Lightsail](docs/deployment/01-deploy-on-aws-lightsail.md): step-by-step server setup for staging and production
- [Environments, branches and deploying](docs/deployment/02-environments.md): how local, staging and production differ, and one-click deploys
- [Data dictionary](docs/data-dictionary.md), [design documents](docs/des/), [specifications](docs/specs/00-overview.md)
