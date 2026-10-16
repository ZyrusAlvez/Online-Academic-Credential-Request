# Deploying on AWS Lightsail (Staging and Production on One Server)

This guide sets up the UPHSL Credential Tracker on a single AWS Lightsail server, with both
environments running side by side:

| | Staging | Production |
|---|---|---|
| Address | `https://staging.<your-name>` | `https://<your-name>` |
| Folder | `/var/www/uphsl-staging` | `/var/www/uphsl-production` |
| Database | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| MySQL user (used by the app) | `uphsl_staging` | `uphsl_prod` |
| Data | Base data + demo accounts | Base data + real data only |
| Email | Sent through Gmail | Sent through Gmail |
| Error details | Shown | Never shown |
| Receipts | R2 folder `staging/` | R2 folder `production/` |

Staging works like production in everything else: real email, the same first registrar, the same
installed packages. That's what makes it a trustworthy place to test a change.

It explains *why* each step is done, not only what to type, so you can fix things when they
go differently. The [Troubleshooting](#troubleshooting) section lists every error we hit while
writing this guide and how to fix it.

**Placeholders used below:**

- `<SERVER_IP>` is your server's static IP, for example `203.0.113.10`.
- `<SERVER_IP_DASHED>` is the same IP written with dashes, for example `203-0-113-10`.
- `<PROD_NAME>` and `<STAGING_NAME>` are the site names you pick in [step 2](#2-choose-the-site-names),
  for example `203-0-113-10.sslip.io` and `staging.203-0-113-10.sslip.io`.

## Contents

1. [Create the Lightsail server](#1-create-the-lightsail-server)
2. [Choose the site names](#2-choose-the-site-names)
3. [Connect with SSH](#3-connect-with-ssh)
4. [Install the system software](#4-install-the-system-software)
5. [Install Composer and Node](#5-install-composer-and-node)
6. [Create the two databases](#6-create-the-two-databases)
7. [Download the code (twice)](#7-download-the-code-twice)
8. [Write each environment's `.env`](#8-write-each-environments-env)
9. [Install, build and fill the databases](#9-install-build-and-fill-the-databases)
10. [Configure Nginx](#10-configure-nginx)
11. [Turn on HTTPS](#11-turn-on-https)
12. [Start the queue workers](#12-start-the-queue-workers) and [the scheduler](#121-start-the-scheduler)
13. [Set up email and receipt storage](#13-set-up-email-and-receipt-storage)
14. [Access the databases with DBeaver](#14-access-the-databases-with-dbeaver)
15. [Deploy updates](#15-deploy-updates)
16. [Recommended hardening](#16-recommended-hardening)
- [Troubleshooting](#troubleshooting)
- [Reference](#reference)

## How the pieces fit

```
Browser ──HTTPS──> Nginx ──> PHP-FPM ──> Laravel (one folder per environment) ──> MySQL
                                                                              └──> Cloudflare R2 (receipts)
Supervisor keeps one queue worker per environment running; the workers send the email notifications.
```

| Software | Job |
|---|---|
| **Nginx** | Web server. Receives every request on ports 80/443 and picks the site by the name the browser asked for |
| **PHP-FPM** | Runs the PHP code. Nginx hands it every page request |
| **MySQL** | Holds both databases |
| **Supervisor** | Keeps the queue workers running and restarts them if they crash |
| **Composer / Node** | Install the PHP libraries and build the CSS/JS. Node is used only at deploy time |

This is a **LEMP** stack (Linux, Nginx, MySQL, PHP).

---

## 1. Create the Lightsail server

### 1.1 Create the instance

In the Lightsail console, click **Create instance**:

| Setting | Choose | Why |
|---|---|---|
| Region | **Singapore** (`ap-southeast-1`) | The closest region to the Philippines, so pages load faster |
| Platform | Linux/Unix | |
| Blueprint | **OS Only → Ubuntu 24.04 LTS** | Do **not** pick the "LAMP" or "Nginx" app blueprints. They are built by Bitnami, put files in non-standard places, and don't let you choose PHP 8.5 |
| Plan | **2 GB RAM** | Enough for two app copies, MySQL and the asset build. 1 GB works only if you add swap space |

### 1.2 Attach a static IP

**Networking → Create static IP** and attach it to the instance. It is free while attached.
Without it, the IP changes every time the instance stops and starts, and your site names stop
pointing at it.

### 1.3 Open HTTPS in the firewall

Lightsail opens only port 22 (SSH) and port 80 (HTTP) by default. Open your instance's
**Networking** tab:

1. **IPv4 Firewall → Add rule**: Application **HTTPS**, TCP, port 443. Under *Source IP addresses*
   click **Add** and enter `0.0.0.0/0` (any IPv4 address).
2. **IPv6 Firewall → Add rule**: the same rule with source `::/0` (any IPv6 address).
3. Check that **HTTP (80)** is allowed in both sections. Let's Encrypt needs it to issue the
   HTTPS certificate.

Leave **SSH (22)** open to everyone. Restricting it to your home IP usually locks you out,
because home IPs change, and it breaks Lightsail's browser SSH.

> **Ports** are numbered doors on one IP: 22 is SSH, 80 is HTTP, 443 is HTTPS. MySQL's port
> (3306) is never opened.

### 1.4 Turn on automatic snapshots

**Snapshots → Automatic snapshots → On.** Lightsail then keeps a daily image of the whole server.

### 1.5 Secure the AWS account

If you are signed in as the AWS **root** user, turn on MFA (two-factor login) under
**Account → Security credentials**. Whoever has that password controls the whole AWS bill.

---

## 2. Choose the site names

You need **names**, not just the IP, for two reasons:

1. **HTTPS.** Let's Encrypt issues certificates for names, after checking that the name leads
   to your server.
2. **Two sites on one IP.** Every request carries the name the visitor typed, and Nginx uses it
   to choose the staging or the production folder.

**DNS** is the system that turns names into IPs. Pick one of these:

| Option | Example | Notes |
|---|---|---|
| **sslip.io** (free, no sign-up) | `<SERVER_IP_DASHED>.sslip.io` and `staging.<SERVER_IP_DASHED>.sslip.io` | A public DNS service that answers with the IP written inside the name. Good for testing and demos, but ugly and run by a third party |
| A domain you buy | `uphsl-tracker.com` | About ₱500–800 a year. Add two `A` records pointing to `<SERVER_IP>`: one for the domain and one for `staging` |
| A school subdomain | `credentials.uphsl.edu.ph` | The best option for real use. Ask the school IT office to point it, and `staging.` under it, to `<SERVER_IP>` |

Check that a name resolves (from any computer):

```bash
nslookup staging.<SERVER_IP_DASHED>.sslip.io     # should answer with <SERVER_IP>
```

Staging uses a **subdomain**, not a path like `/staging`. Browsers keep cookies per name, so
`staging.` keeps the staging login cookie separate from production's. Laravel also expects to
own the whole site from `/`.

The app knows its own addresses: they are set per environment in `config/app.php` (`url`). If
your names differ from the ones there, change them in that file and push, or put
`APP_URL=https://<your name>` in that deployment's `.env`, which overrides it.

You can change names later. It means editing the Nginx `server_name`, the address in
`config/app.php` (then deploying), and running certbot again.

---

## 3. Connect with SSH

The server has no screen or keyboard. **SSH** connects your terminal to a shell on the server:
everything you type runs there, encrypted.

### Option A: the browser

Instance → **Connect** tab → **Connect using SSH**. If Ctrl+V doesn't paste, use the clipboard
icon in the bottom-right corner.

### Option B: your own terminal (more reliable for pasting long blocks)

Download the **default key** from the Connect tab, then on your laptop:

```bash
mkdir -p ~/key
mv ~/Downloads/LightsailDefaultKey-ap-southeast-1.pem ~/key/
chmod 600 ~/key/LightsailDefaultKey-ap-southeast-1.pem    # ssh refuses keys others can read
ssh -i ~/key/LightsailDefaultKey-ap-southeast-1.pem ubuntu@<SERVER_IP>
```

The first time, answer `yes` to *"The authenticity of host ... can't be established"*. Your
laptop then remembers the server's fingerprint, so it can warn you if anyone later impersonates it.

Optional shortcut, so `ssh uphsl` is all you need:

```bash
cat >> ~/.ssh/config <<'EOF'
Host uphsl
    HostName <SERVER_IP>
    User ubuntu
    IdentityFile ~/key/LightsailDefaultKey-ap-southeast-1.pem
EOF
```

The key file is like a house key: never share it or commit it.

### Read the prompt before every command

```
ubuntu@ip-172-26-5-32:~$
  │          │         │ └─ $ = normal user (# would be root)
 user   server name    └─ current folder (~ = your home folder)
```

The server name contains the server's **private IP** (only reachable inside AWS). The public
static IP is what the internet uses, and AWS forwards it to the private one.

You will work as two different users. **Check which one you are before each step**:

| User | Prompt starts with | Use it for |
|---|---|---|
| `ubuntu` | `ubuntu@` | Admin tasks that need `sudo`: installing software, MySQL admin, Nginx, Supervisor |
| `www-data` | `www-data@` | Everything inside the app folders: git, composer, npm, artisan |

`www-data` cannot use `sudo`. If you see `[sudo] password for www-data:`, press **Ctrl+C**, type
`exit`, and run the command again as `ubuntu`.

---

## 4. Install the system software

Ubuntu installs software with **apt**:

- `apt update` refreshes the catalog of available packages. It installs nothing.
- `apt upgrade` installs newer versions of what's already installed, mostly security fixes.
- `apt install` adds new packages.

Ubuntu 24.04's own catalog only has PHP 8.3, and this project needs PHP 8.5. So we add a
**PPA** (an extra package source) maintained by Ondřej Surý, who maintains PHP for Debian. Adding a
PPA means trusting its owner with software that runs as admin, so only add well-known ones.

As `ubuntu`, run these one at a time:

```bash
sudo apt update && sudo apt upgrade -y
```

If a purple screen asks which services to restart, press **Enter**.

```bash
sudo add-apt-repository -y ppa:ondrej/php
```

```bash
sudo apt install -y nginx mysql-server supervisor git unzip certbot python3-certbot-nginx \
  php8.5-fpm php8.5-cli php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip php8.5-intl php8.5-bcmath
```

The `php8.5-*` packages are PHP extensions: MySQL access, non-English text (ñ), HTTP calls (R2),
unzipping, date/number formatting, and exact decimal math. `certbot` and its Nginx plugin are
used for HTTPS in step 11.

Receipts can be up to 5 MB, but PHP's default upload limit is 2 MB. Raise it:

```bash
sudo sed -i 's/^upload_max_filesize.*/upload_max_filesize = 8M/; s/^post_max_size.*/post_max_size = 10M/' /etc/php/8.5/fpm/php.ini
sudo systemctl restart php8.5-fpm
```

### Check

```bash
php -v              # PHP 8.5.x
nginx -v
mysql --version     # MySQL 8.0.x (Ubuntu 24.04's version works fine)
systemctl status nginx php8.5-fpm mysql supervisor    # each "active (running)"; press q to exit
```

Open `http://<SERVER_IP>` in a browser. You should see **"Welcome to nginx!"**.

---

## 5. Install Composer and Node

There are three package managers, one per level:

| Tool | Installs | Reads | Into |
|---|---|---|---|
| apt | System software | Ubuntu's catalog | The whole server |
| Composer | PHP libraries (Laravel, Livewire, Flux) | `composer.json` / `composer.lock` | `vendor/` |
| npm | Build tools (Vite, Tailwind) | `package.json` / `package-lock.json` | `node_modules/` |

`vendor/` and `node_modules/` are not in Git, so the server downloads them itself. The lock files
pin the exact versions used in development.

Don't use `apt install composer nodejs`. Ubuntu's Composer pulls in a second PHP (8.3), and
Ubuntu's Node (v18) is too old for Tailwind 4.

### 5.1 Composer, with a checksum check

**Start in your home folder.** In `/`, `ubuntu` isn't allowed to create files, and the download
fails silently.

```bash
cd ~
EXPECTED=$(curl -s https://composer.github.io/installer.sig)
curl -s -o composer-setup.php https://getcomposer.org/installer
ACTUAL=$(sha384sum composer-setup.php | cut -d' ' -f1)
echo "expected: $EXPECTED"; echo "actual:   $ACTUAL"
```

A **checksum** is a fingerprint of the genuine file. Continue only if both lines match:

```bash
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

`/usr/local/bin` is where hand-installed programs go. It's on your `PATH` (the folders the shell
searches for commands), so `composer` now works anywhere.

### 5.2 Node 22

NodeSource runs an apt source with current Node versions. Download its setup script and look at
it before running it as admin:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x -o nodesource_setup.sh
less nodesource_setup.sh          # q to quit
sudo bash nodesource_setup.sh
sudo apt install -y nodejs
rm nodesource_setup.sh
```

### Check

```bash
composer --version    # Composer 2.x, "PHP version 8.5.x"
node -v               # v22.x
npm -v
```

---

## 6. Create the two databases

| Term | Meaning |
|---|---|
| MySQL server | The program running on this machine |
| Database | A separate set of tables. You create two |
| User | A MySQL login (separate from Linux users) |
| Grant | Permission for a user on a specific database |

On Ubuntu, MySQL's admin (`root`) has **no password**. MySQL lets you in only when the Linux user
asking is root, which you become with `sudo`. So the admin login is simply `sudo mysql`, and it
works only on the server itself.

Create the databases now. The app users are created in [step 8.3](#83-create-the-app-database-users),
once the `.env` files exist, so the passwords can be written into MySQL and `.env` in one go.

```bash
sudo mysql -e "
CREATE DATABASE uphsl_credential_tracker_staging CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE uphsl_credential_tracker         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- `utf8mb4` stores full Unicode: ñ, accented names, emoji. Avoid MySQL's older `utf8`, a broken
  legacy version.
- `utf8mb4_unicode_ci` is the **collation** (rules for comparing and sorting text). `ci` means
  case-insensitive, which is what Laravel expects.

Confirm MySQL is invisible to the internet:

```bash
sudo ss -ltnp | grep 3306       # should show 127.0.0.1:3306 (localhost only)
```

---

## 7. Download the code (twice)

### Why `www-data`

Nginx and PHP-FPM run as the Linux user **`www-data`**, not as root. If the app ever had a bug that
let someone run commands, they'd only get `www-data`'s limited powers. Laravel must also *write*
files while it runs (`storage/`, `bootstrap/cache/`). So the code belongs to `www-data`, and every
app command runs as `www-data`. That way every file has one consistent owner and you never get
"Permission denied". `www-data`'s home folder is `/var/www`, so for that user, `~` means `/var/www`.

### Why two folders

`php artisan optimize` saves the final settings, including which database to use, into one file
per folder (`bootstrap/cache/config.php`). **One folder can serve only one environment.** Two
folders also let staging run newer code than production while you test it.

### Do it

As `ubuntu`:

```bash
sudo chown www-data:www-data /var/www
```

Run this line **on its own**, because it starts a new shell:

```bash
sudo -u www-data bash
```

Your prompt now starts with `www-data@`. Then:

```bash
cd /var/www
git clone -b production https://github.com/ZyrusAlvez/Online-Academic-Credential-Request.git uphsl-production
git clone -b staging https://github.com/ZyrusAlvez/Online-Academic-Credential-Request.git uphsl-staging
```

`-b` checks out a branch: each folder follows its own branch (see step 15).

### Check

```bash
ls -l /var/www                 # both folders, owned by www-data
cd uphsl-production && git log --oneline -3 && ls -a
```

There is no `.env`, `vendor/` or `node_modules/`. That's on purpose: `.gitignore` keeps them out
of Git. **Secrets never go in Git**, especially in a public repository.

---

## 8. Write each environment's `.env`

### 8.1 What `.env` is

The code is identical in both folders. Each folder's `.env` decides which environment it is and
holds all its secrets. `config/*.php` reads `.env`. `.env.example` is the template, committed
without secrets.

The file is deliberately small. Anything that follows from `APP_ENV` is a default in `config/`:

| | Staging | Production |
|---|---|---|
| Database | `uphsl_credential_tracker_staging` | `uphsl_credential_tracker` |
| Debug mode (error details) | on | always off |
| Mailer | Gmail SMTP once `MAIL_USERNAME` is set | same |
| Receipts folder | `staging/` | `production/` |
| Address | `https://staging.<…>` from `config/app.php` | `https://<…>` from `config/app.php` |
| Secure (HTTPS-only) login cookie | on, because the address is `https://` | same |

So `.env` holds only the environment name, the app key, the database login and the secrets.

> **After `php artisan optimize`, Laravel reads its cached copy, not `.env`.** Whenever you edit
> `.env` later, run `php artisan optimize` again, or the change silently does nothing.

### 8.2 Create the files

As `www-data`. Production:

```bash
cd /var/www/uphsl-production
cp .env.example .env
chmod 600 .env
nano .env
```

`chmod 600` means only the owner (`www-data`) can read or write the file. In **nano**, arrow keys
move, **Ctrl+W** searches, **Ctrl+O** then Enter saves, and **Ctrl+X** exits. Set:

```dotenv
APP_ENV=production
INITIAL_REGISTRAR_EMAIL=<the registrar's email>
INITIAL_REGISTRAR_PASSWORD=<a strong password you choose and remember>
```

Staging:

```bash
cd /var/www/uphsl-staging
cp .env.example .env
chmod 600 .env
nano .env
```

```dotenv
APP_ENV=staging
INITIAL_REGISTRAR_EMAIL=<the registrar's email>
INITIAL_REGISTRAR_PASSWORD=<a strong password>
```

Use a different password from production's. **Check `APP_ENV` in both files.** One left at the template's `local` would use the laptop
address `http://localhost:8000` in every email link and turn off the secure login cookie.

| Setting | Staging | Production |
|---|---|---|
| `APP_ENV` | `staging` | `production` |
| `APP_KEY` | Leave empty; generated in step 9 | Leave empty; generated in step 9 |
| `DB_USERNAME` / `DB_PASSWORD` | Written automatically in 8.3 | Written automatically in 8.3 |
| `INITIAL_REGISTRAR_EMAIL` / `_PASSWORD` | Required (its own password) | Required |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | Step 13 | Step 13 |
| `RECEIPTS_DISK`, `R2_*` | Step 13 | Step 13 |

**Keep production's secrets off staging.** Give staging its own registrar password and, ideally,
its own Gmail account (step 13). Then a leak of staging's `.env` reveals nothing that opens
production.

`APP_KEY` encrypts and signs the login cookies. If it leaks, someone can forge logins. If it
changes, everyone is logged out. Each environment gets its own.

### 8.3 Create the app database users

Each environment gets its **own** MySQL user, which can access only its own database. Staging has
demo accounts with the password `password`, so it's the weaker side. If its database password leaked, it still
couldn't open production. This is the **principle of least privilege**.

These blocks generate a random password, create the user with it, and write the same password
into `.env`. Nobody ever sees it, and there is nothing to mistype. Run them as **`ubuntu`**
(`exit` first if you are `www-data`). Each block is safe to paste whole.

Production:

```bash
P=$(openssl rand -hex 20)
sudo mysql -e "CREATE USER 'uphsl_prod'@'localhost' IDENTIFIED BY '$P';
GRANT ALL ON uphsl_credential_tracker.* TO 'uphsl_prod'@'localhost';"
sudo -u www-data sed -i "s|^DB_USERNAME=.*|DB_USERNAME=uphsl_prod|; s|^DB_PASSWORD=.*|DB_PASSWORD=$P|" /var/www/uphsl-production/.env
unset P
```

Staging:

```bash
P=$(openssl rand -hex 20)
sudo mysql -e "CREATE USER 'uphsl_staging'@'localhost' IDENTIFIED BY '$P';
GRANT ALL ON uphsl_credential_tracker_staging.* TO 'uphsl_staging'@'localhost';"
sudo -u www-data sed -i "s|^DB_USERNAME=.*|DB_USERNAME=uphsl_staging|; s|^DB_PASSWORD=.*|DB_PASSWORD=$P|" /var/www/uphsl-staging/.env
unset P
```

- `openssl rand -hex 20` prints 40 random hex characters, with no symbols that could break SQL
  or `.env`.
- `@'localhost'` means the user can log in only from this machine.
- `sed -i "s|old|new|"` replaces a line in the file in place.

You don't need to save these passwords. Only the app uses them, and they live in `.env`. If one
ever needs replacing, run the same block with `ALTER USER` instead of `CREATE USER` and without
the `GRANT` line. (`.env` files can be read by `ubuntu` with `sudo grep '^DB_PASSWORD' <file>`.)

### Check, without printing any secrets

```bash
sudo grep -E '^(APP_ENV|DB_USERNAME)' /var/www/uphsl-*/.env
sudo grep -c '^DB_PASSWORD=[0-9a-f]\{40\}$' /var/www/uphsl-*/.env    # both files ":1"
sudo ls -l /var/www/uphsl-*/.env                                    # both "-rw------- www-data www-data"
sudo mysql -e "SHOW GRANTS FOR 'uphsl_prod'@'localhost'; SHOW GRANTS FOR 'uphsl_staging'@'localhost';"
```

Each user's grants should name only its own database.

To edit a `.env` later from the `ubuntu` user, open it as its owner: `sudo -u www-data nano <file>`.
A plain `nano .env` as `ubuntu` shows an **empty screen**, because `ubuntu` can't read the file.
Don't save that empty screen.

---

## 9. Install, build and fill the databases

| Command | What it does |
|---|---|
| `composer install --no-dev --optimize-autoloader` | Downloads the PHP libraries. `--no-dev` skips development tools (Pest, PHPStan, Pint), so less code ships to the server. `--optimize-autoloader` builds a class index for speed |
| `php artisan key:generate` | Creates `APP_KEY` and writes it into `.env` |
| `npm ci && npm run build` | Installs the exact build tools from the lock file, then Vite compiles Tailwind and JS into `public/build/` with hashed file names (so browsers fetch new versions after each change) |
| `php artisan migrate --seed --force` | **Migrations** create the tables from `database/migrations/`. **Seeders** fill in the starter rows. `--force` confirms, since production asks "are you sure?" |
| `php artisan app:reset-demo` | Staging (and local) only: rebuilds all tables and loads base + demo data. It refuses to run in production, and refuses to start without the `INITIAL_REGISTRAR_*` settings |
| `php artisan optimize` | Caches config, routes and views |

Both environments install with `--no-dev`, so staging runs exactly the packages production runs.

Every step needs its own folder's `vendor/`, build and key. Nothing is shared between the folders.

Run each chain as **one line** and as `www-data` (`sudo -u www-data bash` on its own line first).
`&&` stops at the first failure, so nothing gets cached with a broken setting.

Production:

```bash
cd /var/www/uphsl-production && composer install --no-dev --optimize-autoloader --no-interaction && php artisan key:generate && npm ci && npm run build && php artisan migrate --seed --force && php artisan optimize
```

Staging:

```bash
cd /var/www/uphsl-staging && composer install --no-dev --optimize-autoloader --no-interaction && php artisan key:generate && npm ci && npm run build && php artisan app:reset-demo && php artisan optimize
```

The build may print `Generated an empty chunk: "app"`. That's harmless: Livewire and Flux bring
their own JavaScript.

### What the seeders create

| Seeder | Creates |
|---|---|
| `DepartmentSeeder` | The 15 colleges and offices |
| `CredentialTypeSeeder` | The 7 credential types and their signing order (`workflow_steps`) |
| `RegistrarSeeder` | The first registrar account, from `INITIAL_REGISTRAR_EMAIL` / `INITIAL_REGISTRAR_PASSWORD` |
| `DemoSeeder` (staging only, via `app:reset-demo`) | Demo students, department staff and requests in every status, all at `@example.com` with the password `password`. The demo requests are approved by your registrar |

The first registrar is needed because someone has to be able to log in and create every other
account. `RegistrarSeeder` works the same in every environment: it creates the account from
`INITIAL_REGISTRAR_EMAIL` / `INITIAL_REGISTRAR_PASSWORD` **only if no account with that email
exists**, and never changes it afterwards. Re-seeding can't reset a password you changed in the
app. Without a password it refuses to run, so no environment gets a guessable admin password.

Demo accounts use `example.com`, a domain reserved for examples. The app never sends email to it,
so demo data on staging can't bounce or use up Gmail's daily limit.

After your first production login, change the registrar password in the app. Then remove the
`INITIAL_REGISTRAR_PASSWORD` value from production's `.env` and run `php artisan optimize`. Keep
the email line: the seeder uses it to see that the account already exists.

### Check

In each folder:

```bash
php artisan about              # Environment, Debug Mode (ON in staging, OFF in production), URL
php artisan db:show --counts   # which database, and how many rows per table
```

| | Production | Staging |
|---|---|---|
| Database | `uphsl_credential_tracker` | `uphsl_credential_tracker_staging` |
| Username | `uphsl_prod` | `uphsl_staging` |
| `departments` | 15 | 15 |
| `credential_types` | 7 | 7 |
| `workflow_steps` | 18 | 18 |
| `users` | 1 | many (demo accounts) |
| `credential_requests` | 0 | many (sample requests) |

---

## 10. Configure Nginx

A **server block** tells Nginx: for requests to *this name*, serve files from *this folder*, and
hand PHP to PHP-FPM.

| Line | Purpose |
|---|---|
| `root .../public` | **Only `public/` is visible to the internet.** If it pointed at the project folder, anyone could download `/.env` |
| `client_max_body_size 10m` | Largest accepted upload (Nginx's default is 1 MB; receipts can be 5 MB) |
| `try_files $uri $uri/ /index.php?...` | Serve a real file (CSS, JS, images) directly if it exists; otherwise hand the request to Laravel's `public/index.php` (the "front controller") |
| `fastcgi_pass unix:/run/php/php8.5-fpm.sock` | Reach PHP-FPM through a **socket**, a local connection between two programs on the same machine |
| `fastcgi_hide_header X-Powered-By` | Don't announce the PHP version to visitors |
| `location ~ /\.(?!well-known).*` with `deny all` | Block dot-files (`.env`, `.git`) as a second lock. `.well-known` stays open for Let's Encrypt |

As `ubuntu`, check the socket path first:

```bash
ls /run/php/      # php8.5-fpm.sock
```

Write the production site (paste the whole block):

```bash
sudo tee /etc/nginx/sites-available/uphsl-production > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name <PROD_NAME>;

    root /var/www/uphsl-production/public;
    index index.php;
    charset utf-8;
    client_max_body_size 10m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
```

Write the staging site:

```bash
sudo tee /etc/nginx/sites-available/uphsl-staging > /dev/null <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name <STAGING_NAME>;

    root /var/www/uphsl-staging/public;
    index index.php;
    charset utf-8;
    client_max_body_size 10m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
```

Replace `<PROD_NAME>` and `<STAGING_NAME>` before pasting. `tee` writes its input into a file
(with `sudo`, since the folder belongs to root). The `<<'EOF' ... EOF` heredoc feeds the lines in;
the quotes around `'EOF'` stop bash from expanding `$uri`, so Nginx gets the text unchanged.

Check that each file saved completely (it must end with two `}` lines):

```bash
cat /etc/nginx/sites-available/uphsl-production
cat /etc/nginx/sites-available/uphsl-staging
```

Turn the sites on and the welcome page off:

```bash
sudo ln -s /etc/nginx/sites-available/uphsl-production /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/uphsl-staging /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
```

`sites-available` holds every site's settings, and `sites-enabled` holds **symlinks** (shortcuts)
to the ones that are switched on.

Always test before applying, because a typo could take both sites down:

```bash
sudo nginx -t                   # "syntax is ok" and "test is successful"
sudo systemctl reload nginx     # apply without dropping connected visitors
```

### Check

- `http://<PROD_NAME>` shows the production login page.
- `http://<STAGING_NAME>` shows the staging login page.
- From your laptop: `curl -I http://<PROD_NAME>/.env` answers `403 Forbidden`.

The browser says **"Not secure"** until the next step. Don't type real passwords yet.

---

## 11. Turn on HTTPS

Plain HTTP sends everything, passwords included, as readable text. HTTPS encrypts it, and needs a
**certificate** that proves the server really is that name, signed by a Certificate Authority
browsers trust.

**Let's Encrypt** is a free, trusted authority, and **certbot** requests and installs its
certificates. Let's Encrypt checks ownership with the **HTTP challenge**: certbot places a token
at `http://<name>/.well-known/acme-challenge/...`, and Let's Encrypt fetches it over the internet.
That's why port 80 stays open and `.well-known` isn't blocked. Certificates last 90 days, and
certbot installs a timer that renews them automatically.

As `ubuntu`, **on its own line** (it asks questions):

```bash
sudo certbot --nginx -d <PROD_NAME> -d <STAGING_NAME>
```

Enter a real email (for expiry warnings), agree to the terms (`Y`), and the EFF question is
optional. It should end with **"Successfully deployed certificate"**.

### Check

- `https://<PROD_NAME>` and `https://<STAGING_NAME>` show the padlock.
- The `http://` addresses redirect to `https://`.
- `cat /etc/nginx/sites-available/uphsl-production` shows the lines certbot added
  (`# managed by Certbot`): `listen 443 ssl`, the certificate paths, and an HTTP→HTTPS redirect block.
- `sudo certbot renew --dry-run` reports that all simulated renewals succeeded.

### Login cookies are HTTPS-only automatically

Because the site's address starts with `https://`, `config/session.php` marks the login cookie **secure**:
the browser sends it only over HTTPS, so it can never leak over plain HTTP. Nothing to do. Check it
as `www-data` in each folder:

```bash
php artisan config:show session.secure      # true
```

While a site is still on plain HTTP (before this step), logins fail with "419 Page Expired",
because the browser refuses to send a secure cookie over HTTP. Finishing HTTPS fixes it.

---

## 12. Start the queue workers

Sign-up verification and password-reset emails are sent immediately. **Request notifications are
queued**: they are saved in the `jobs` table and sent by a worker process running in the
background. Supervisor keeps one worker per environment running and restarts it if it stops.

As `ubuntu` (safe to paste whole):

```bash
sudo tee /etc/supervisor/conf.d/uphsl.conf > /dev/null <<'EOF'
[program:uphsl-production-queue]
command=php /var/www/uphsl-production/artisan queue:work --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true

[program:uphsl-staging-queue]
command=php /var/www/uphsl-staging/artisan queue:work --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
EOF
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
```

Both programs should show **RUNNING**. `--max-time=3600` makes each worker restart every hour,
which keeps memory use in check.

### Check

```bash
sudo mysql -e "SELECT COUNT(*) FROM uphsl_credential_tracker.jobs;"    # drops to 0 as jobs are sent
```

### 12.1 Start the scheduler

The app also has **scheduled tasks**, listed in `routes/console.php`; for example, expired
registrar API tokens are removed daily. Laravel needs **one** cron entry per environment, which
runs `schedule:run` every minute; Laravel then decides which tasks are due. Never add separate
cron entries for individual commands, or tasks run twice.

As `ubuntu` (safe to paste whole):

```bash
sudo tee /etc/cron.d/uphsl-scheduler > /dev/null <<'EOF'
* * * * * www-data cd /var/www/uphsl-production && php artisan schedule:run >> /dev/null 2>&1
* * * * * www-data cd /var/www/uphsl-staging && php artisan schedule:run >> /dev/null 2>&1
EOF
```

Files in `/etc/cron.d` include a user column (`www-data`), so the tasks run as the app's user.
Check the task list with `sudo -u www-data php /var/www/uphsl-production/artisan schedule:list`.

The turnaround predictions need no extra software. They are calculated in PHP from the trained
model file committed at `resources/ml/turnaround-model.json`, so Python is only needed on the
machine where the model is retrained (`ml/`), not on the server.

---

## 13. Set up email and receipt storage

### 13.1 Email (both environments)

Staging sends real email exactly like production, so you can test the real thing before users
see it. Until Gmail is configured, an environment writes its emails to
`storage/logs/laravel.log` instead. Demo accounts use `example.com`, which the app never emails.

Create a Gmail **App Password** by following
[Part A of the mail and storage guide](../setup-mail-and-storage.md); ideally use a second Gmail
account for staging. Then, as `www-data`, in each folder:

```bash
cd /var/www/uphsl-production      # then the same in /var/www/uphsl-staging
nano .env
```

```dotenv
MAIL_USERNAME=your.account@gmail.com
MAIL_PASSWORD=<the 16-character App Password, without spaces>
```

With these set, the app uses Gmail's SMTP server (`smtp.gmail.com:587`) and sends from
`MAIL_USERNAME`; those are defaults in `config/mail.php`. Remove any `MAIL_MAILER=log` line, which
would override them.

```bash
php artisan optimize && php artisan app:test-mail your.own@gmail.com
```

`app:test-mail` sends immediately (not queued), so it tests the Gmail settings alone. Expected:
`Sent. Check the inbox (and the spam folder).` If it says *"Written to storage/logs/laravel.log"*,
`MAIL_USERNAME` is empty, a `MAIL_MAILER=log` line overrides it, or `optimize` wasn't run.

Lightsail blocks only port 25 for outgoing mail, and Gmail uses 587, so this works.

### 13.2 Receipts (Cloudflare R2)

Follow [Part B of the mail and storage guide](../setup-mail-and-storage.md) to create the bucket
and API token, then put the same `RECEIPTS_DISK=r2` and four `R2_*` lines into **both** `.env` files
and run `php artisan optimize` in each. Check with:

```bash
php artisan app:test-storage       # "Write, read and delete all worked."
```

Receipts then live in R2, not on the server's disk: they survive the server being lost and don't
fill its disk. Each environment writes to its own folder in the bucket (`staging/` or
`production/`).

> A development laptop runs as `local`, so even with the same bucket it writes to its own
> `local/` folder and can't touch staging's or production's receipts. Keeping laptops on
> `RECEIPTS_DISK=local` is still simpler: no development files in the shared bucket.

---

## 14. Access the databases with DBeaver

MySQL listens only on localhost and port 3306 stays closed, so DBeaver connects through an
**SSH tunnel**: it logs in to the server over SSH (port 22, your key) and talks to MySQL from
inside the server.

```
DBeaver on your laptop ──SSH (22)──> server ──> 127.0.0.1:3306 (MySQL)
```

### 14.1 Create a personal MySQL user

Don't use the app's users or MySQL root:

- The **app users** are **service accounts** that belong to the app. If the app shared your
  login, changing your password would break the site, and you couldn't remove your access later.
- **Root** can only log in through `sudo mysql` on the server (Ubuntu's `auth_socket` login), and
  it can drop whole databases. Keep it for admin tasks: creating users, grants, backups.

Create a user for yourself (replace `<your-name>`; MySQL usernames are case-sensitive, and quotes
are needed if the name has a hyphen). Generate its password and keep it in a password manager.
You'll type this one into DBeaver.

```bash
openssl rand -hex 20
```

```bash
sudo mysql
```

```sql
CREATE USER '<your-name>'@'localhost' IDENTIFIED BY '<the-password>';
GRANT SELECT ON uphsl_credential_tracker.* TO '<your-name>'@'localhost';
GRANT ALL ON uphsl_credential_tracker_staging.* TO '<your-name>'@'localhost';
SHOW GRANTS FOR '<your-name>'@'localhost';
EXIT;
```

**Production is read-only for people**, and that's deliberate:

- Request statuses must change only through the app (`RequestWorkflow`), which also writes the
  append-only `request_events` history and sends notifications. A hand edit skips both.
- Table structure changes only through migrations, tested on staging first. A hand-made change
  makes production drift from the migration files.
- DBeaver commits immediately by default, so there is no undo.

Staging is yours to edit freely. `app:reset-demo` rebuilds it.

Test the login on the server before configuring DBeaver:

```bash
mysql -u '<your-name>' -p -h 127.0.0.1      # mysql> means it works; EXIT;
```

### 14.2 Configure the connection

**New Database Connection → MySQL → Next.** Enlarge the dialog if some tabs are cut off.

**Main** tab (MySQL as seen *from the server*):

| Field | Value |
|---|---|
| Server Host | `127.0.0.1` |
| Port | `3306` |
| Database | `uphsl_credential_tracker` |
| Username / Password | your personal user, with Save password |

**SSH** tab (in the same tab row as Main; in some versions under *Network configs*):

| Field | Value |
|---|---|
| Use SSH Tunnel | ✅ |
| Host/IP | `<SERVER_IP>` |
| Port | `22` |
| User Name | `ubuntu` |
| Authentication Method | **Public Key** (not Password) |
| Private Key | the full path, e.g. `/home/<you>/key/LightsailDefaultKey-ap-southeast-1.pem` (DBeaver doesn't understand `~`) |

Click **Test tunnel configuration**, then **Test Connection** (let DBeaver download the driver).

If you get **"Public Key Retrieval is not allowed"**: on the **Driver properties** tab, set
`allowPublicKeyRetrieval` to `true`. MySQL 8 must fetch the server's key to encrypt the password
when SSL isn't used. That's safe here, because the SSH tunnel already encrypts everything.

Finish, then **Edit Connection → General**: name it `UPHSL Production` and set Connection type to
**Production** (red, with confirmations).

**Staging:** select the connection, press **Ctrl+C**, **Ctrl+V**, and edit the copy: Database
`uphsl_credential_tracker_staging`, name `UPHSL Staging`, Connection type **Development**.

### Check

| Connection | Run | Expected |
|---|---|---|
| Production | `SELECT COUNT(*) FROM departments;` | `15` |
| Production | `UPDATE departments SET name = name WHERE id = 1;` | **UPDATE command denied** (read-only works) |
| Staging | `SELECT email, role FROM users LIMIT 5;` | Demo accounts |

---

## 15. Deploy updates

Each server folder follows its own GitHub branch: `staging` → `/var/www/uphsl-staging`,
`production` → `/var/www/uphsl-production`. A change goes feature branch → `staging` (test it) →
`production`, as explained in [02 — Environments, branches and deploying](02-environments.md).

### 15.1 Install the deploy command (once)

The deploy command is part of the repository (`scripts/server/uphsl-deploy`). As `ubuntu`:

```bash
sudo install -m 755 /var/www/uphsl-staging/scripts/server/uphsl-deploy /usr/local/bin/uphsl-deploy
```

`install` copies the file and makes it executable (`-m 755`). You only do this once: every
staging deploy installs the newest version from the repository by itself.

For the chosen environment, `uphsl-deploy` fetches GitHub, switches the folder to its branch,
installs packages, builds the assets, runs the migrations, caches the settings and restarts the
queue workers. It stops at the first error.

- **No deploy without an app key.** If the folder's `.env` has no `APP_KEY`, it stops before
  changing anything and prints the `key:generate` command to run.
- **Production only takes what staging is running.** If the code on the `production` branch
  differs from the code deployed on staging, it stops with *"Deploy staging first"*.
- `queue:restart` makes the workers load the new code. Without it, they keep running the old
  code from memory.

### 15.2 Use it

Normally you don't run it yourself: GitHub Actions runs it after every merge into `staging`, and
after your approval for `production` (see [02 — Environments](02-environments.md#cicd-what-runs-where)).
The keys it uses are set up in the server step of that page.

As a fallback, double-click `scripts/deploy-staging.sh` (Ubuntu) or `scripts\deploy-staging.bat`
(Windows) on your own computer, or the `deploy-production` ones. The one-time SSH setup is in
[02 — Environments](02-environments.md#manual-deploy-fallback).

Or on the server, as `ubuntu`:

```bash
uphsl-deploy staging        # then test the change on the staging site
uphsl-deploy production
```

**Run the tests (`composer qa`) on your laptop before pushing, not on the server.** They use
their own `uphsl_credential_tracker_testing` database, which the server doesn't have, and
production has no test tools installed.

---

## 16. Recommended hardening

These steps go beyond the base setup, but are worth doing before real users arrive.

### 16.1 Put staging behind a password

Both apps run as `www-data`, so a break-in through staging could read production's `.env`. Staging
is also the weaker side: public, showing error details (debug mode), with demo logins using `password`. Nginx basic
auth makes the browser ask for a username and password before showing anything on staging:

```bash
sudo apt install -y apache2-utils
sudo htpasswd -c /etc/nginx/.htpasswd-staging staging      # asks for a password
sudo chown root:www-data /etc/nginx/.htpasswd-staging && sudo chmod 640 /etc/nginx/.htpasswd-staging
sudo nano /etc/nginx/sites-available/uphsl-staging
```

Inside the `server` block that has `listen 443 ssl`, add:

```nginx
    auth_basic "Staging";
    auth_basic_user_file /etc/nginx/.htpasswd-staging;
```

Then run `sudo nginx -t && sudo systemctl reload nginx`.

The stronger fix is running staging as its own Linux user with its own PHP-FPM pool, or on a
separate server. Lightsail can copy this server from a snapshot when that's needed.

### 16.2 Nightly database backup

Lightsail snapshots copy the whole disk daily. A nightly `mysqldump` also gives you a plain file
you can restore one database from:

```bash
sudo mkdir -p /var/backups/mysql
sudo tee /etc/cron.d/uphsl-backup > /dev/null <<'EOF'
0 1 * * * root mysqldump --single-transaction uphsl_credential_tracker | gzip > /var/backups/mysql/uphsl-$(date +\%F).sql.gz && find /var/backups/mysql -name '*.sql.gz' -mtime +14 -delete
EOF
```

This runs every night at 01:00 (Asia/Manila), keeping 14 days. The `\%` is required inside cron
files. To restore: `gunzip -c <file>.sql.gz | sudo mysql uphsl_credential_tracker`. A backup you
have never restored is a hope, not a backup, so try a restore into a scratch database once.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `[sudo] password for www-data:` | You are still `www-data`, which can't use `sudo` | **Ctrl+C**, `exit`, `whoami` should say `ubuntu`, then run it again |
| `sha384sum: composer-setup.php: No such file or directory` | You were in `/` (see the prompt `:/$`), where `ubuntu` can't create files. `curl -s` hid the error | `cd ~` and repeat. Use `-fsSL` instead of `-s` to see download errors |
| `^[[200~mysql: command not found` or `~php: not found` | The terminal sent invisible "paste" markers, often right after closing nano or mysql | **Ctrl+C** and paste again, or type the command. `reset` restores the terminal |
| Lines after `sudo -u www-data bash` or `nano` never ran | Pasting several lines that start an interactive program loses the rest | Paste interactive commands (`bash`, `nano`, `mysql`, `less`, `certbot`) on their own |
| `Access denied for user 'uphsl_prod'@'localhost' (using password: YES)` | The password in `.env` doesn't match MySQL's. Common causes: placeholder text left in, or a typo | Test directly with `mysql -u uphsl_prod -p -h 127.0.0.1`. Reset both sides with the step 8.3 block, using `ALTER USER`, then `php artisan config:clear` |
| The same error again after fixing `.env` | `optimize` cached the old password | `php artisan config:clear`, then re-run the command, then `php artisan optimize` |
| `Set INITIAL_REGISTRAR_PASSWORD in .env to create the first registrar.` (or `..._EMAIL ...`) | The registrar seeder needs both settings to create the account | Set them in `.env`, then run only that seeder: `php artisan db:seed --class=RegistrarSeeder --force && php artisan optimize` |
| `app:reset-demo`: `Set INITIAL_REGISTRAR_EMAIL and INITIAL_REGISTRAR_PASSWORD in .env first. Nothing was changed.` | The demo reset recreates the registrar, so it checks first | Set both in staging's `.env`, run `php artisan optimize`, and run it again |
| `Failed opening required '.../vendor/autoload.php'` | `composer install` was never run in that folder | Run the step 9 chain in that folder. Each folder needs its own install |
| `certbot: command not found` | certbot isn't installed | `sudo apt install -y certbot python3-certbot-nginx` |
| nano shows an empty `.env` | As `ubuntu`, you can't read a file owned by `www-data` with mode 600 | **Ctrl+X** without saving, then `sudo -u www-data nano .env` |
| Registrar login fails after changing `INITIAL_REGISTRAR_PASSWORD` | The seeder only creates the account; it never changes an existing one | Use the old password, or reset it through **Forgot password** |
| "419 Page Expired" on login | The address is `https://…` (secure cookie) but the site is still on plain HTTP | Finish step 11 (HTTPS) |
| No emails arrive | `MAIL_USERNAME`/`MAIL_PASSWORD` missing, a `MAIL_MAILER=log` line, `optimize` wasn't run, no queue worker is running, or the address is on `example.com` (never emailed) | Step 13.1 (`app:test-mail`) and step 12 (`supervisorctl status`) |
| DBeaver: "Public Key Retrieval is not allowed" | MySQL 8 password exchange without SSL | Driver properties → `allowPublicKeyRetrieval` = `true` |
| DBeaver: `Access denied for user '<name>'` | Wrong username or password, or the user doesn't exist | `sudo mysql -e "SELECT user, host FROM mysql.user;"`, then test with `mysql -u <name> -p -h 127.0.0.1` |
| DBeaver can't read the key file | A Snap or Flatpak DBeaver can't read hidden folders like `~/.ssh` | Keep the key in a normal folder such as `~/key/` |
| `ssh: Identity file ... not accessible` then `Permission denied (publickey)` | The key path is wrong | `ls` the folder and use the real file name in `-i` |
| A page shows 502 Bad Gateway | PHP-FPM isn't running, or the socket path is wrong | `systemctl status php8.5-fpm` and `ls /run/php/` |
| A page shows 500 Server Error | An application error | `sudo -u www-data tail -50 /var/www/uphsl-<env>/storage/logs/laravel.log` |

**Debugging habits:**

- Check `whoami` and the prompt before running commands.
- Read each result before running the next command.
- Test credentials directly with the tool itself (`mysql`, `ssh`) before blaming the app.

---

## Reference

### Accounts and what they can reach

| Account | Kind | Production DB | Staging DB | Used by |
|---|---|---|---|---|
| `uphsl_prod` | MySQL service account | full | none | The production app (password only in its `.env`) |
| `uphsl_staging` | MySQL service account | none | full | The staging app (password only in its `.env`) |
| your personal user | MySQL person account | read-only | full | You, in DBeaver |
| MySQL `root` | Admin | full | full | `sudo mysql` on the server only |
| `ubuntu` | Linux admin | | | You, over SSH, with `sudo` |
| `www-data` | Linux service user | | | Nginx, PHP-FPM, queue workers, app commands |

Passwords for programs live in config files. Passwords for people live in a password manager.
Never paste either into chats, documents or Git.

### Where things are

| What | Where |
|---|---|
| App folders | `/var/www/uphsl-production`, `/var/www/uphsl-staging` |
| Environment settings | `<app folder>/.env` |
| App logs | `<app folder>/storage/logs/laravel.log` |
| Nginx sites | `/etc/nginx/sites-available/uphsl-*` |
| Nginx errors | `/var/log/nginx/error.log` |
| PHP settings | `/etc/php/8.5/fpm/php.ini` |
| Queue workers | `/etc/supervisor/conf.d/uphsl.conf` |
| Scheduler | `/etc/cron.d/uphsl-scheduler` |
| Deploy command | `/usr/local/bin/uphsl-deploy` |
| Certificates | `/etc/letsencrypt/live/` (managed by certbot) |

### Everyday commands

```bash
uphsl-deploy staging | production                       # deploy (as ubuntu)
sudo supervisorctl status                               # are the workers running?
sudo systemctl reload nginx                             # after editing Nginx (run nginx -t first)
sudo -u www-data php /var/www/uphsl-<env>/artisan optimize    # after editing a .env
sudo -u www-data php /var/www/uphsl-<env>/artisan about       # environment summary
sudo -u www-data php /var/www/uphsl-<env>/artisan db:show --counts
```
