# Environments, Branches and Deploying

The system runs in exactly **three environments**. Each has its own settings file (`.env`), its own
database and its own receipt folder, so nothing done in one can change another. This page explains
how they differ, how code moves between them, and how to deploy with one click.

For setting up the server itself, see [01 — Deploying on AWS Lightsail](01-deploy-on-aws-lightsail.md).

## The three environments

| | Local | Staging | Production |
|---|---|---|---|
| **Purpose** | Building and trying changes on your own computer | Testing a change on the real server before users see it; demos | The live system used by students, offices and the registrar |
| **Where** | Your computer, `composer dev` | Server folder `/var/www/uphsl-staging` | Server folder `/var/www/uphsl-production` |
| **Address** | http://localhost:8000 | https://staging.18-139-77-227.sslip.io | https://18-139-77-227.sslip.io |
| **Git branch** | your feature branch | `staging` | `production` (the default branch) |
| **`APP_ENV`** | `local` | `staging` | `production` |
| **Database** | `uphsl_credential_tracker_local` | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| **Data** | Base data + demo accounts and requests | Base data + demo accounts and requests | Base data + real data only |
| **Email** | Real (Gmail), **every email redirected to your own inbox** | Real (Gmail) | Real (Gmail) |
| **Error details** | Shown | Shown | Never shown |
| **Receipts folder** (R2 or disk) | `local/` | `staging/` | `production/` |
| **Installed packages** | Including development tools (tests, code checks) | Same as production (`--no-dev`) | No development tools (`--no-dev`) |

A fourth, internal mode, `testing`, is used only by the automated tests. They run on their own
database, `uphsl_credential_tracker_testing`, and refuse to run on any other, so `composer qa`
never touches your local, staging or production data.

### Staging behaves like production

Staging exists to catch problems before real users do, so it works **exactly like production**:

- it sends real email the same way;
- it gets its first registrar account the same way (`INITIAL_REGISTRAR_EMAIL` / `_PASSWORD`);
- it installs the same packages, runs the same queue worker and scheduler, and uses HTTPS.

It differs only where it has to:

| Difference | Why |
|---|---|
| Its own database and receipt folder | So testing can never change real records |
| Demo data allowed (`php artisan app:reset-demo`) | So every screen has something to show; production refuses demo data |
| Error details shown | So a failing change explains itself; production shows a plain error page instead |

### Demo data never emails anyone

Demo accounts use `@example.com`, a domain reserved for examples (RFC 2606). The app never sends
email to reserved domains in any environment, so demo data can't bounce or use up Gmail's
~500-a-day limit. Real people who sign up on staging still get real email.

On local, every email goes to your own inbox no matter who it was addressed to (Laravel's
`Mail::alwaysTo`). Set `MAIL_REDIRECT_TO` to use an inbox other than `MAIL_USERNAME`.

## What goes in `.env`, and what doesn't

Everything that follows from the environment is a default in `config/`, chosen by `APP_ENV`. The
`.env` file holds only what differs per deployment, mostly secrets:

| In `.env` (see `.env.example`) | Derived from `APP_ENV` in `config/` |
|---|---|
| `APP_ENV`: `local`, `staging` or `production` | Site address (`config/app.php`) |
| `APP_KEY`: the encryption key, unique per deployment | Database name (`config/database.php`) |
| `DB_USERNAME`, `DB_PASSWORD` | Debug mode and log level |
| `MAIL_USERNAME`, `MAIL_PASSWORD`: Gmail. Without them, email goes to the log file | Mailer, and the local inbox redirect (`config/mail.php`) |
| `INITIAL_REGISTRAR_EMAIL`, `INITIAL_REGISTRAR_PASSWORD` | Secure (HTTPS-only) login cookie (`config/session.php`) |
| `RECEIPTS_DISK`, `R2_*`: receipt storage | Receipt folder and R2 endpoint (`config/filesystems.php`) |

Any derived value can still be overridden by adding it to `.env` (for example `APP_URL`,
`DB_DATABASE` or `APP_DEBUG`), but normally you shouldn't need to. After editing a server's
`.env`, run `php artisan optimize` in that folder; Laravel reads a cached copy otherwise.

## Branches: how a change reaches users

```
 feature branch ──── PR ────►  staging  ──── PR ────►  production (default)
       │                         │                        │
 composer qa (CI)          deploy-staging            deploy-production
                           → staging server           → production server
```

| Branch | Holds | Deployed to |
|---|---|---|
| `feat/…`, `fix/…` | One change in progress, branched from `staging` | Nowhere (your computer) |
| `staging` | The version being tested | The staging server |
| `production` | The version users run; the repository's default branch | The production server |

There is no `main` branch: `production` is the default, so what you see first on GitHub is exactly
what users run.

### Releasing a change, step by step

1. **Start a branch from `staging`:** `git switch staging && git pull && git switch -c feat/my-change`.
   Commit your work, run `composer qa`, then `git push -u origin feat/my-change`.
2. **Move it to staging.** On GitHub, open a pull request from your branch **into `staging`**
   (GitHub suggests `production`, the default, so change the base) and merge it once CI passes.
3. **Deploy staging:** run `deploy-staging` (below), then test the change on the staging site.
4. **Release it.** Open a pull request from `staging` into `production` and merge it.
   (Terminal: `git fetch origin && git push origin origin/staging:production`.)
5. **Deploy production:** run `deploy-production` and type `production` to confirm.

The pull requests leave a record of what was released and when.

**Production can only take what staging is running.** The server's deploy command checks that
every commit on the `production` branch is already deployed on staging. If not, it stops with
*"Deploy staging first"* and changes nothing.

## One-click deploy

The `scripts/` folder has a deploy script for each environment, for Ubuntu and for Windows:

| | Ubuntu / Linux | Windows |
|---|---|---|
| Staging | `scripts/deploy-staging.sh` | `scripts\deploy-staging.bat` |
| Production | `scripts/deploy-production.sh` | `scripts\deploy-production.bat` |

Each one:

1. shows which commit is about to be deployed (the latest on that GitHub branch);
2. for production, asks you to type `production` to confirm;
3. connects to the server over SSH and runs `uphsl-deploy staging` or `uphsl-deploy production`;
4. says whether it finished or failed, and waits for Enter so you can read the output.

The script itself never changes a branch. It deploys whatever the branch holds on GitHub.

### One-time setup on your computer

The scripts connect to the server through an SSH shortcut named **`uphsl`**. You need the
server's key file: Lightsail console → instance → **Connect** tab → download the default key.

**Ubuntu / Linux:**

```bash
mkdir -p ~/key && mv ~/Downloads/LightsailDefaultKey-ap-southeast-1.pem ~/key/
chmod 600 ~/key/LightsailDefaultKey-ap-southeast-1.pem
cat >> ~/.ssh/config <<'EOF'
Host uphsl
    HostName 18.139.77.227
    User ubuntu
    IdentityFile ~/key/LightsailDefaultKey-ap-southeast-1.pem
EOF
ssh uphsl exit && echo "SSH works"
```

To launch with a double-click, right-click the `.sh` file in Files → **Properties** → turn on
**Executable as Program** once. Then right-click → **Run as a Program**. The script opens a
terminal window by itself. From a terminal, `./scripts/deploy-staging.sh` works too.

**Windows 10/11** (the SSH client is built in). Save the key as
`C:\Users\<you>\.ssh\LightsailDefaultKey-ap-southeast-1.pem`, then in PowerShell:

```powershell
New-Item -ItemType Directory -Force "$env:USERPROFILE\.ssh" | Out-Null
icacls "$env:USERPROFILE\.ssh\LightsailDefaultKey-ap-southeast-1.pem" /inheritance:r /grant:r "${env:USERNAME}:R"
Add-Content "$env:USERPROFILE\.ssh\config" "Host uphsl`n    HostName 18.139.77.227`n    User ubuntu`n    IdentityFile ~/.ssh/LightsailDefaultKey-ap-southeast-1.pem"
ssh uphsl exit; if ($?) { "SSH works" }
```

The `icacls` line makes the key readable only by you; SSH refuses keys that others can read.
Then double-click `scripts\deploy-staging.bat`. With Git for Windows installed, the script also
shows which commit is being deployed.

To use an SSH shortcut with a different name, set `UPHSL_SSH_HOST` before running a script.

The first time you connect, answer `yes` to *"The authenticity of host ... can't be
established"*. Your computer then remembers the server's fingerprint.

### What runs on the server

`uphsl-deploy` lives in the repository at `scripts/server/uphsl-deploy` and is installed on the
server at `/usr/local/bin/uphsl-deploy`. For the chosen environment it:

1. fetches GitHub and, for production, checks that staging already runs every production commit;
2. switches the folder to its branch and fast-forwards it;
3. installs packages (`composer install --no-dev`), builds the CSS/JS, runs the migrations and
   caches the settings;
4. restarts the queue workers so they load the new code.

It stops at the first error, so a failed step never leaves half-cached settings behind.

When `scripts/server/uphsl-deploy` changes in the repository, reinstall it on the server (as
`ubuntu`):

```bash
sudo install -m 755 /var/www/uphsl-staging/scripts/server/uphsl-deploy /usr/local/bin/uphsl-deploy
```

## Quick reference

| I want to… | Do this |
|---|---|
| Work on a feature | Branch from `staging`; `composer dev`, then `composer qa` before pushing |
| Reload demo data | `php artisan app:reset-demo` (local or staging folder) |
| Test a change on the server | PR your branch → `staging`, then `deploy-staging` |
| Release to users | PR `staging` → `production`, then `deploy-production` |
| Change a setting on the server | Edit that folder's `.env` as `www-data`, then `php artisan optimize` |
| See which version a server runs | `ssh uphsl "sudo -u www-data git -C /var/www/uphsl-production log -1 --oneline"` |
