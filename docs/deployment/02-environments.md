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
 feature branch ─ merge ─► staging  ────── PR (from staging only) ──────►  production (default)
       │                     │                                               │
                       CI → deploy to staging                     CI → deploy to production
                       → health check                              → health check
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
   Commit your work and `git push -u origin feat/my-change`.
2. **Merge it into `staging`:** `git switch staging && git merge --no-ff feat/my-change && git push`.
   No pull request is needed for this step.
3. **Staging deploys itself.** The push starts the *deploy* workflow: CI (`composer qa` and the
   rest) runs on the merged code, and only if it passes is the staging server updated and its
   `/up` health check verified. Test the change on the staging site.
4. **Open a pull request from `staging` into `production`** and merge it once CI is green. Merging
   is the release: the *deploy* workflow runs CI again, then updates production and runs its
   health check. There is no separate approval step.

The pull requests and the Environments page (Code tab → *Deployments*) keep a record of what was
released, when, and who merged it.

## CI/CD: what runs where

Tests and deploys run on **GitHub Actions**, never on the servers:

| When | Workflow | What it does |
|---|---|---|
| Every pull request | `ci.yml` | On a fresh Ubuntu machine with PHP 8.5, MySQL 8.0 (as on the server) and Node 22: `composer validate`, `composer audit` (known vulnerabilities), `npm run build`, then `composer qa` (Pint, PHPStan, all Pest tests) |
| Pull request into `production` | `ci.yml` | Also `release-source`: fails unless the pull request comes from `staging` |
| Push (merge) to `staging` or `production` | `deploy.yml` | Runs `ci.yml` again, then the `deploy` job: SSH to the server → `uphsl-deploy <branch>` → `curl <site>/up` |
| Actions tab → *deploy* → **Run workflow** | `deploy.yml` | Re-deploys a branch, e.g. after fixing a server setting |

**Branch protection.** `production` only accepts pull requests whose `qa` and `release-source`
checks passed. It doesn't require them to be up to date, because each release adds a merge commit
that `staging` never gets. Neither branch can be force-pushed or deleted. Branches are merged into
`staging` and pushed directly; the account is an admin, so GitHub lets that push past the
pull-request rule. The safety net is the deploy workflow, which won't deploy code that fails `qa`.

**GitHub Environments.** The deploy job runs in the `staging` or `production` environment.
Each environment holds its own SSH key secret (`DEPLOY_SSH_KEY`) and only accepts deploys from its
own branch; neither needs a reviewer, since the merge is the decision. The server
address and host fingerprints are repository variables (`DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS`).

**The deploy keys can only deploy.** Each key is listed on the server in
`~ubuntu/.ssh/authorized_keys` with a forced command:

```
restrict,command="/usr/local/bin/uphsl-deploy staging" ssh-ed25519 AAAA… github-actions-staging
```

Whatever the connection asks for, the server runs only that deploy, with no shell and no
forwarding. A leaked key can do nothing but redeploy the branch that's already on GitHub.

**Server setup (once), as `ubuntu`.** Add each environment's public key with its forced command.
The private halves live only in the GitHub secrets:

```bash
cat >> ~/.ssh/authorized_keys <<'EOF'
restrict,command="/usr/local/bin/uphsl-deploy staging" ssh-ed25519 <staging public key> github-actions-staging
restrict,command="/usr/local/bin/uphsl-deploy production" ssh-ed25519 <production public key> github-actions-production
EOF
```

To replace a key: create a new pair (`ssh-keygen -t ed25519 -N "" -C github-actions-staging -f key`),
put the private half in the environment's `DEPLOY_SSH_KEY` secret (Settings → Environments), and
swap the public line in `authorized_keys`.

**Production can only take what staging is running.** The server's deploy command checks that
the code on the `production` branch is identical to the code deployed on staging. If not, it stops
with *"Deploy staging first"* and changes nothing. It compares content, not commits, because
merging the release pull request adds a merge commit that exists only on `production`.

## Manual deploy (fallback)

Normally GitHub Actions deploys for you. If Actions is down, or you need to redeploy straight
from your computer, the `scripts/` folder has a deploy script for each environment, for Ubuntu and
for Windows:

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

1. stops if the folder's `.env` has no `APP_KEY`, since every page that uses the session would fail
   with a 500 error (the message shows the command that creates one);
2. fetches GitHub and, for production, checks that its code is identical to what staging runs;
3. switches the folder to its branch and fast-forwards it;
4. installs packages (`composer install --no-dev`), builds the CSS/JS, runs the migrations and
   caches the settings;
5. restarts the queue workers so they load the new code;
6. for staging, installs the newest version of `uphsl-deploy` itself, so changes to the script
   reach the server with the next staging deploy and are used for the next production release.

It stops at the first error, so a failed step never leaves half-cached settings behind.

## Quick reference

| I want to… | Do this |
|---|---|
| Work on a feature | Branch from `staging`; `composer dev` (`composer qa` is optional, CI runs it) |
| Reload demo data | `php artisan app:reset-demo` (local or staging folder) |
| Test a change on the server | Merge your branch into `staging` and push; it deploys once `qa` passes |
| Release to users | Merge the PR `staging` → `production`; it deploys once `qa` passes |
| Redeploy without a code change | Actions → *deploy* → **Run workflow** on that branch |
| Change a setting on the server | Edit that folder's `.env` as `www-data`, then `php artisan optimize` |
| See which version a server runs | `ssh uphsl "sudo -u www-data git -C /var/www/uphsl-production log -1 --oneline"` |
