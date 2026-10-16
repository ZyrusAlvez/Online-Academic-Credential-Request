# Setting Up Email (Gmail) and Receipt Storage (Cloudflare R2)

Both are configured in each deployment's `.env`. Until you finish these steps the app keeps
working: emails are written to `storage/logs/laravel.log` and receipts are stored on the machine.

| | Local | Staging | Production |
|---|---|---|---|
| Email | Sent through Gmail, **every email redirected to your own inbox** | Sent through Gmail | Sent through Gmail |
| Receipts | Keep `RECEIPTS_DISK=local` (see the note in B6) | R2 bucket, folder `staging/` | R2 bucket, folder `production/` |

---

## Part A — Email with Gmail (every environment)

### A1. Pick the Gmail account

Use a dedicated account if you can, for example `uphsl.credential.tracker@gmail.com`. Every email
the system sends will come from this address.

Production, staging and your own computer all send email the same way. A **second** Gmail account
for staging and local (for example `uphsl.tracker.testing@gmail.com`) keeps production's App
Password off the staging server; repeat A2–A4 for it.

### A2. Turn on 2-Step Verification

1. Open https://myaccount.google.com/security while signed in to that Gmail account.
2. Under **How you sign in to Google**, open **2-Step Verification** and turn it on.

App Passwords (next step) only exist when 2-Step Verification is on.

### A3. Create an App Password

1. Open https://myaccount.google.com/apppasswords.
2. App name: `UPHSL Credential Tracker`, then **Create**.
3. Copy the 16-character password Google shows. It is shown only once.

Use this App Password, never your normal Gmail password.

### A4. Put it in each `.env`

```dotenv
MAIL_USERNAME=uphsl.credential.tracker@gmail.com
MAIL_PASSWORD=abcdefghijklmnop        # the App Password, without spaces
```

The rest is already set in `config/mail.php`: with these two values set, the app uses SMTP through `smtp.gmail.com:587`,
emails are sent from `MAIL_USERNAME`, and the sender name is "UPHSL Office of the Registrar".
(`MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` can still override these.)
Then run `php artisan optimize` so the cached settings pick up the change.

**On your own computer** (`APP_ENV=local`) every email is redirected to `MAIL_USERNAME`'s inbox,
whoever it was addressed to, so you see exactly what students would get and nobody else is
emailed. To send them to a different inbox, add `MAIL_REDIRECT_TO=you@example.org`.

Emails to reserved example domains (`example.com`, `*.test`, …), which all demo accounts use, are
never sent in any environment.

### A5. Test it

In the environment's folder (on the server) or the project folder (locally):

```bash
php artisan app:test-mail your.own.address@gmail.com
```

Expected: `Sent. Check the inbox (and the spam folder).` The first emails may land in spam; mark
them as "Not spam".

### A6. Keep the queue worker running

Notifications are sent in the background by a queue worker. On the server, Supervisor keeps one
running per environment (deployment guide, step 12). Locally, `composer dev` starts one.

**Limits:** a Gmail account can send to about 500 recipients a day.

**Links in emails** use the environment's address from `config/app.php` (or `APP_URL` in `.env`,
which overrides it).

---

## Part B — Receipt storage with Cloudflare R2

### B1. Create a Cloudflare account

Sign up at https://dash.cloudflare.com/sign-up and verify your email.

### B2. Turn on R2

1. In the dashboard sidebar open **R2 Object Storage**.
2. Click **Purchase R2 Plan** / **Get started** and add a payment method (card or PayPal).

The free tier (10 GB stored per month, plus generous read/write allowances) is far more than this
system needs, but Cloudflare asks for a payment method before enabling R2.

### B3. Create the bucket

1. **R2 Object Storage → Create bucket**.
2. Name: `uphsl-credential-receipts` (lowercase, no spaces).
3. Location: **Automatic**. Default storage class: **Standard**.
4. **Create bucket**.

Leave **public access off**: do not enable the `r2.dev` subdomain or connect a custom domain.
Receipts are private; the app streams them only to the student who uploaded them and to registrar
accounts.

### B4. Copy your Account ID

On the **R2 Object Storage** overview page, copy the **Account ID** shown under *Account details*.

### B5. Create an API token for the app

1. On the R2 overview page click **Manage API tokens** (or **{} API → Manage API tokens**).
2. **Create Account API token** (or *Create API token*).
3. Token name: `uphsl-credential-tracker`.
4. Permissions: **Object Read & Write**.
5. Specify bucket(s): **Apply to specific buckets only** → `uphsl-credential-receipts`.
6. TTL: **Forever**, then **Create API Token**.
7. Copy the **Access Key ID** and the **Secret Access Key**. The secret is shown only once.
   (You do not need the "Token value" or the "jurisdiction-specific endpoint".)

### B6. Put it in `.env`

```dotenv
RECEIPTS_DISK=r2
R2_ACCOUNT_ID=0123456789abcdef0123456789abcdef
R2_ACCESS_KEY_ID=your-access-key-id
R2_SECRET_ACCESS_KEY=your-secret-access-key
R2_BUCKET=uphsl-credential-receipts
```

The endpoint is built from `R2_ACCOUNT_ID` in `config/filesystems.php`. Put the same values in
both server `.env` files (staging and production) and run `php artisan optimize` in each.

> **Keep your own computer on `RECEIPTS_DISK=local`.** The bucket is foldered by environment, and
> local demo resets delete the `local/` receipts. Using local storage on your computer keeps
> development files out of the shared bucket.

### B7. Test both environments

On the server, in each folder:

```bash
php artisan app:test-storage     # staging folder → staging/, production folder → production/
```

Expected for each: `Write, read and delete all worked.`

### B8. Move the staging demo receipts to R2

```bash
php artisan app:reset-demo
```

Run it in the staging folder. It reloads the staging demo data, and its sample receipts are now
uploaded to the bucket.
Production has no receipts yet, so nothing needs moving there.

### B9. Restart the app

Run `php artisan queue:restart` in each folder so the workers pick up the new settings. In the
Cloudflare dashboard the bucket will show `staging/payment-proofs/…` (and later
`production/payment-proofs/…`).

---

## Troubleshooting

| Message | Fix |
|---|---|
| `Username and Password not accepted` (Gmail) | Use the App Password, not your normal password; check 2-Step Verification is on; remove spaces from the App Password. |
| `Connection could not be established` (Gmail) | Check `MAIL_HOST=smtp.gmail.com` and `MAIL_PORT=587`; some networks block port 587. |
| Emails arrive in spam | Normal at first for automated Gmail mail; mark them "Not spam". |
| `SignatureDoesNotMatch` (R2) | Secret Access Key is wrong or has extra spaces; create a new token if unsure. |
| `NoSuchBucket` (R2) | `R2_BUCKET` does not match the bucket name exactly. |
| `AccessDenied` (R2) | The token needs **Object Read & Write** on this bucket. |
| `Could not resolve host` (R2) | `R2_ACCOUNT_ID` is empty or wrong. |
| Changes in `.env` seem ignored | Run `php artisan optimize` (server) or restart `composer dev` (local); then `php artisan queue:restart`. |

## Security

- `.env` is never committed. If a password or key ever leaks, revoke it (Google App Passwords
  page / Cloudflare API tokens page) and create a new one.
- The R2 token can only touch this one bucket, so a leaked key cannot reach anything else in your
  Cloudflare account.
