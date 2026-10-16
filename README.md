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

There are exactly **two** environments. Both are configured in one `.env` file, and `APP_ENV`
selects which one runs. Any other value stops the app at startup.

| | Staging | Production |
|---|---|---|
| Purpose | Development, demos and automated tests | Real use |
| Start | `composer dev` | `composer prod` |
| Address | http://localhost:8000 | http://localhost:8001 |
| `APP_ENV` | `staging` (the default in `.env`) | `production` (set by `composer prod`) |
| Database | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Data | Base data plus demo accounts and sample requests | Base data and real data only |
| Email | Written to `storage/logs/laravel.log` | Sent through Gmail |
| Receipts | `staging/` folder in the R2 bucket | `production/` folder in the R2 bucket |
| Debug mode | On | Always off |

Base data means the 15 departments, the 7 credential types with their signing workflows, and
the first registrar account.

### Running commands

Commands run in staging by default. Put `APP_ENV=production` in front to run one in production:

```bash
php artisan migrate                       # staging
APP_ENV=production php artisan migrate    # production
```

### Rules that keep production safe

- Automated tests run only on the staging database. They wipe it and reload the demo data
  afterwards, and they refuse to start on any database whose name does not end in `_staging`.
- Demo data and `php artisan app:reset-demo` (reload the demo data) work only in staging.
- Each environment has its own database and its own receipts folder, so nothing done in
  staging can change production.

## More documentation

- [Credentials and testing](docs/credentials.md): demo accounts and test steps
- [Email and receipt storage setup](docs/setup-mail-and-storage.md): Gmail and Cloudflare R2
- [Deploying on AWS Lightsail](docs/deployment/01-deploy-on-aws-lightsail.md): step-by-step server setup for staging and production
- [Data dictionary](docs/data-dictionary.md), [design documents](docs/des/), [specifications](docs/specs/00-overview.md)
