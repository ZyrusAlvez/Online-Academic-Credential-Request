# Security

What protects the UPHSL Credential Tracker, layer by layer: the application, the server, and the
path code takes to reach it. The last two sections cover vulnerabilities that were patched and
what is still open.

Last reviewed: 2026-11-15.

## Contents

1. [Accounts and sign-in](#1-accounts-and-sign-in)
2. [Who can see and do what](#2-who-can-see-and-do-what)
3. [Uploaded files](#3-uploaded-files)
4. [Web attacks: CSRF, XSS, SQL injection, mass assignment](#4-web-attacks-csrf-xss-sql-injection-mass-assignment)
5. [Audit trail](#5-audit-trail)
6. [Environments and secrets](#6-environments-and-secrets)
7. [The server](#7-the-server)
8. [Getting code to the server](#8-getting-code-to-the-server)
9. [Vulnerabilities patched](#9-vulnerabilities-patched)
10. [Still open](#10-still-open)
11. [Keeping it secure](#11-keeping-it-secure)

---

## 1. Accounts and sign-in

| Protection | How | Where |
|---|---|---|
| Strong passwords | At least 8 characters with upper and lower case, a number and a symbol. A live checklist shows the rules while typing (REQ-01-03, REQ-09-02) | `AppServiceProvider` (`Password::defaults`) |
| Passwords stored hashed | bcrypt via the `hashed` cast; never stored or logged in plain text | `App\Models\User` |
| Sign-in brute force | 5 wrong passwords per email + IP, then a lockout with a countdown | `resources/views/livewire/auth/login.blade.php` |
| Session fixation | The session ID is regenerated on every sign-in (password and Google) | login page, `GoogleController` |
| Email ownership | A six-digit code, stored only as a hash, expires after 10 minutes, allows 5 wrong guesses, and a new one can be requested every 30 seconds at most (REQ-01-23) | `App\Actions\Auth\SendVerificationCode`, `ConfirmVerificationCode` |
| Unverified accounts | Every app page needs a verified email (`verified` middleware) | `routes/web.php` |
| Google sign-in | Accepted only when Google says the email is verified; throttled to 10 a minute per IP; switched off (404) unless the Google keys are set (REQ-01-24) | `App\Actions\Auth\SignInWithGoogle`, `routes/auth.php` |
| Account enumeration | "Forgot password" gives the same answer whether or not the account exists. A wrong sign-in says only "These credentials do not match"; "deactivated" shows only after the right password | `forgot-password.blade.php`, login page |
| Deactivated accounts | Refused at sign-in, and signed out on their next request if deactivated while signed in, including "remember me" sessions (REQ-01-07) | `App\Http\Middleware\EnsureUserIsActive` |
| No privilege escalation at sign-up | Self-registered and Google accounts are always requesters: `role` and `is_active` are not mass assignable | `User::$fillable` |
| Staff can't lock themselves out | An admin can't change their own role or deactivate their own account, so an admin always remains (REQ-18-10) | `App\Policies\UserPolicy`, `App\Actions\Staff\*` |
| First account | The only seeded account is the admin from `INITIAL_ADMIN_EMAIL`/`INITIAL_ADMIN_PASSWORD`. No password is ever in the repository. The seeder creates it only when missing and never changes it | `AdminSeeder` |
| Session cookie | `Secure` (HTTPS only) whenever the address is `https://`, `HttpOnly` (unreadable by JavaScript), `SameSite=Lax`; 2-hour idle lifetime | `config/session.php` |

## 2. Who can see and do what

There are four roles: Requester, Registrar, Department Staff and Admin (spec 18). Every check is
made twice:

1. **By route**: the `role` middleware guards each area (`/student`, `/registrar`, `/department`,
   `/admin`, `/records`). Livewire's follow-up requests (`/livewire/update`) re-run the same check
   (`Livewire::addPersistentMiddleware`), so a component can't be driven from the wrong role.
2. **By record**: a policy decides whether *this* user may see or act on *this* request or file
   (`app/Policies/`).

| Rule | Detail |
|---|---|
| Requesters see only their own requests and files | Anyone else gets **404 Not Found**, not 403, so reference numbers and file IDs can't be probed (`denyAsNotFound`) |
| Department staff see only requests sent to their office | Checked against the request's office visits |
| Uploaded files are staff-only | IDs, proofs of payment, SPAs and requirement copies: the owner, the Registrar and the Admin. Department staff never see them |
| Actions follow the workflow | A requester can edit only a request returned for clarification, cancel only before acceptance, and pay only while payment is awaited. Only the Registrar releases, and only when the request is ready |
| One evaluator at a time | Another registrar can't act on a request someone else is evaluating (REQ-16-09) |
| Status changes go through one place | `App\Services\RequestWorkflow` is the only code that changes a request's or step's status. It checks the transition is allowed and writes the ledger |

## 3. Uploaded files

The only uploads are a requester's valid ID, a representative's SPA and IDs, optional copies of
documentary requirements, and proofs of payment. No credential documents are ever transferred.

| Protection | How |
|---|---|
| File types | `jpg`, `jpeg`, `png`, `webp` and `pdf` only, checked by content as well as name (`mimes:`) |
| Size | 5 MB per file (`max:5120`); Nginx refuses any request body over 10 MB |
| Never public | Stored outside `public/`: on the server's private disk (`storage/app/private/<env>/`) or in a private Cloudflare R2 bucket. No file has a public URL |
| Unguessable names | Saved under a random UUID, never the uploader's file name, in a folder per user or request |
| Served only after a policy check | `PaymentProofController`, `IdentityDocumentController` and `RequestDocumentController` authorize first, then stream the file |
| No content sniffing, no caching | Served with `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store` (`ServesPrivateFile`) |
| Environments kept apart | Each environment has its own folder (`local/`, `staging/`, `production/`), so a staging reset never touches production files |
| Abandoned uploads | Livewire's temporary uploads expire after 5 minutes and are cleaned up after 24 hours |

## 4. Web attacks: CSRF, XSS, SQL injection, mass assignment

| Attack | Protection |
|---|---|
| Cross-site request forgery | Laravel's CSRF token on every form and every Livewire request; `SameSite=Lax` cookies |
| Cross-site scripting | Blade's `{{ }}` escapes all output. The one place that builds HTML (`components/office-visits.blade.php`) escapes names with `e()` first. Laravel's mail layout is the only other unescaped output |
| SQL injection | Eloquent and the query builder bind every value. The raw SQL in reports only inserts fixed enum values from the code; user input is always a bound parameter |
| Mass assignment | Models list their fillable fields; `role`, `is_active` and `google_id` are set explicitly, never from user input |
| Oversized Livewire payloads | Livewire's guards: 1 MB payload, 50 method calls and 200 components per request (`config/livewire.php`) |
| Strict models outside production | `Model::shouldBeStrict()` catches silently discarded attributes during development |

## 5. Audit trail

| Log | What it records | Protection |
|---|---|---|
| `request_events` | Every status change of every request: who, when, from what, to what | **Append-only**: the model throws on any update or delete (`App\Models\RequestEvent`) |
| `activity_logs` | Every action across the system, including sign-ins, sign-outs, failed sign-ins (with IP address), refused deactivated accounts, and account, role and settings changes (spec 14) | **Append-only** the same way (`App\Models\ActivityLog`). A password change is logged without the password; the Google link is never logged |

Only the Admin can read the activity log (`ActivityLogPolicy`).

## 6. Environments and secrets

| Protection | How |
|---|---|
| Only three environments | `AppServiceProvider` refuses any `APP_ENV` other than `local`, `staging` or `production` |
| No error details in production | `config/app.php` forces debug off when `APP_ENV=production`, whatever `.env` says |
| Production can't be wiped by accident | `app:reset-demo` and the demo seeder refuse to run in production; `app:reset` in production needs the typed phrase `reset production` |
| Tests can't touch real data | `tests/TestCase.php` refuses to run unless the database name ends in `_testing` |
| No secrets in Git | `.env`, `auth.json` and `storage/app/private/` are git-ignored. Every password and key lives only in each server folder's `.env` |
| `.env` locked down on the server | Owned by `www-data`, mode `600`: no other user can read it |
| Demo accounts never email | Recipients on reserved example domains are dropped in every environment (`DropUndeliverableRecipients`); locally every email is redirected to the developer's own inbox |
| Deploys never seed | Deploys only run migrations, so a deploy can't create or change accounts |

## 7. The server

One AWS Lightsail server (Ubuntu 24.04) hosts both staging and production. Setup:
[docs/deployment/01-deploy-on-aws-lightsail.md](docs/deployment/01-deploy-on-aws-lightsail.md).

| Protection | How | Checked |
|---|---|---|
| Firewall | Only ports 22 (SSH), 80 (HTTP, redirects to HTTPS) and 443 (HTTPS) are open | Lightsail console |
| HTTPS | Let's Encrypt certificates for both sites, renewed automatically by `certbot.timer`; HTTP redirects to HTTPS | timer active, 2026-11-15 |
| SSH | Key only: password login is off (`PasswordAuthentication no`), and root can't log in with a password | `sshd -T`, 2026-11-15 |
| MySQL not reachable from outside | Listens on `127.0.0.1` only; port 3306 is never opened. DBeaver connects through an SSH tunnel | `ss -ltn`, 2026-11-15 |
| Least-privilege database users | `uphsl_prod` reaches only the production database and `uphsl_staging` only staging. Personal (DBeaver) users are **read-only on production**. MySQL root logs in only through `sudo mysql` on the server | guide §8.3, §14 |
| Only `public/` is served | Nginx's root is each app's `public/` folder, so `.env`, the code and uploads are never reachable | |
| Dot-files blocked | Nginx denies `/.env`, `/.git` and other dot-files, as a second lock | `/.env` answers **403**, 2026-11-15 |
| Version hiding | `fastcgi_hide_header X-Powered-By` and `expose_php = Off` hide the PHP version | 2026-11-15 |
| Operating system updates | `unattended-upgrades` installs Ubuntu's security updates automatically | active, 2026-11-15 |
| PHP updates | PHP comes from the `ondrej/php` source, which automatic updates **don't** cover. It's updated by hand: see [§11](#11-keeping-it-secure) | |
| Backups | Lightsail automatic snapshots: a daily image of the whole server | guide §1.4 |
| AWS account | MFA on the AWS root user | guide §1.5 |

## 8. Getting code to the server

| Protection | How |
|---|---|
| Nothing deploys without passing `qa` | Every deploy first runs `ci.yml`: `composer validate`, **`composer audit`** (stops on any known vulnerability in a dependency), the front-end build, Pint, PHPStan (level 6) and every Pest test |
| Production only takes tested code | Pull requests into `production` must come from `staging` (`release-source.yml`). On the server, `uphsl-deploy` refuses a production deploy unless its code is identical to what staging is already running |
| Branch protection | `production` accepts only pull requests whose `qa` and `release-source` checks passed; no force-pushes or deletion on `staging` or `production` |
| Deploy keys can only deploy | Each environment has its own SSH key, stored only as a GitHub Environment secret. On the server it is pinned to a forced command (`restrict,command="/usr/local/bin/uphsl-deploy <env>"`): no shell, no forwarding. A leaked key can only redeploy what's already on GitHub |
| Server fingerprint pinned | GitHub Actions connects only to the server whose host key is in `DEPLOY_KNOWN_HOSTS` |
| No broken deploys | `uphsl-deploy` stops if the folder's `.env` has no `APP_KEY`, and stops at the first failing step |
| Health check | After each deploy, GitHub Actions checks the site's `/up` page |
| Production dependencies only | Servers install with `composer install --no-dev`: no test or debugging tools in production |

## 9. Vulnerabilities patched

| Date | What | Fix |
|---|---|---|
| 2026-11-15 | **PHP2Shell** (CVE-2026-109618, CVE-2026-109619): two critical PHP flaws that let anyone, without an account, run code on the server as `www-data`. Exploited in the wild and on CISA's Known Exploited Vulnerabilities list. CVE-2026-109619 is reached through PHP's session upload progress, which runs on any POST before the app's code, so no code change could protect against it. The server was on PHP 8.5.11, the last vulnerable version | PHP upgraded to **8.5.12** on the server (`apt install --only-upgrade 'php8.5-*'`, PHP-FPM restarted). Covers both staging and production |
| 2026-11-14 | `laravel/framework`: task output injection in Concurrency processes (CVE-2026-128083, low) | Updated to 12.72 |
| 2026-11-14 | `phpseclib/phpseclib`: CPU-exhaustion DoS through unbounded PBKDF iterations (GHSA-w3p9-g4p2-h3rr) | Updated to 4.0.3 |
| 2026-11 | Symfony components: the 2026-11-04 security advisories | Updated |
| 2026-11 | `symfony/mime` (CVE-2026-45070) | Updated to 7.4.22 |
| 2026-11 | `livewire/livewire` (GHSA-mhf7-xp6r-6qrc) | Updated to 4.4.8 |

The dependency advisories were caught by `composer audit` in CI, which held the deploy back until
they were fixed.

## 10. Still open

These are known gaps, most important first:

| Gap | Risk | Fix |
|---|---|---|
| **Check for signs of a PHP2Shell break-in** | The server ran a vulnerable PHP while the flaws were being exploited (from 2026-10-17 until 2026-11-15) | Look for unexpected `.php` files in `public/` and `storage/`, unknown cron jobs, and processes running as `www-data`. If anything looks wrong, rotate every secret in both `.env` files (database passwords, `APP_KEY`, Gmail app password, R2 keys) |
| Staging is public | Staging shows error details and has demo logins with a published password. Both apps run as `www-data`, so a break-in through staging could read production's `.env` | Put staging behind a password (guide §16.1). The stronger fix is a separate Linux user and PHP-FPM pool for staging, or a separate server |
| No nightly database dump | Snapshots restore the whole server, not one database | Guide §16.2 |
| No security headers | No `Strict-Transport-Security`, `X-Frame-Options`/`frame-ancestors` (clickjacking) or `Content-Security-Policy` | Add them in Nginx or a middleware |
| Nginx version shown | The `Server` header says `nginx/1.24.0 (Ubuntu)` | Uncomment `server_tokens off;` in `/etc/nginx/nginx.conf` |
| Sign-up isn't rate-limited | A script could create many accounts (each still has to verify its email before using the app) | A throttle on the register page |
| PHP isn't auto-updated | The next PHP security release needs someone to install it | See [§11](#11-keeping-it-secure) |

## 11. Keeping it secure

- **Dependencies:** `composer audit` runs on every deploy and blocks it when an advisory appears.
  Fix it on a `fix/` branch with `composer update <package>`, then merge into `staging`.
- **PHP and the server**, at least monthly and whenever a PHP security release is announced
  (as `ubuntu`):

  ```bash
  sudo apt update
  sudo apt upgrade              # includes the php8.5-* packages from ondrej/php
  sudo systemctl restart php8.5-fpm
  php -v
  ```

- **Secrets:** passwords for programs live only in the server's `.env` files; passwords for
  people live in a password manager. Never paste either into chats, documents or Git.
- **New features** touching sign-in, uploads, payments or permissions go through the
  `laravel-security` checklist before merging.
