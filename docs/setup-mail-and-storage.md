# Setting Up Email (Gmail) and Receipt Storage (Cloudflare R2)

Both are configured in the single `.env` file. Until you finish these steps the app keeps working:
emails are written to `storage/logs/laravel.log` and receipts are stored on this computer.

| | Staging | Production |
|---|---|---|
| Email | Always written to the log file (demo accounts never get real email) | Sent through Gmail |
| Receipts | R2 bucket, folder `staging/` | R2 bucket, folder `production/` |

---

## Part A — Email with Gmail (production)

### A1. Pick the Gmail account

Use a dedicated account if you can, for example `uphsl.credential.tracker@gmail.com`. Every email
the system sends will come from this address.

### A2. Turn on 2-Step Verification

1. Open https://myaccount.google.com/security while signed in to that Gmail account.
2. Under **How you sign in to Google**, open **2-Step Verification** and turn it on.

App Passwords (next step) only exist when 2-Step Verification is on.

### A3. Create an App Password

1. Open https://myaccount.google.com/apppasswords.
2. App name: `UPHSL Credential Tracker`, then **Create**.
3. Copy the 16-character password Google shows. It is shown only once.

Use this App Password, never your normal Gmail password.

### A4. Put it in `.env`

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=uphsl.credential.tracker@gmail.com
MAIL_PASSWORD=abcdefghijklmnop        # the App Password, without spaces
MAIL_FROM_ADDRESS=                    # leave empty: emails are sent from MAIL_USERNAME
MAIL_FROM_NAME="UPHSL Office of the Registrar"
```

### A5. Test it

```bash
APP_ENV=production php artisan app:test-mail your.own.address@gmail.com
```

Expected: `Sent. Check the inbox (and the spam folder).` The first emails may land in spam; mark
them as "Not spam".

### A6. Keep the queue worker running

Notifications are sent in the background. `composer prod` already starts the queue worker; on a
server, run `APP_ENV=production php artisan queue:work` permanently (e.g. with Supervisor).

**Limits:** a Gmail account can send to about 500 recipients a day.

**Links in emails** use `APP_URL_PRODUCTION`. `http://localhost:8001` only works on this computer;
set it to the real public address when the system is hosted.

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
R2_ENDPOINT="https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com"   # leave as is
```

### B7. Test both environments

```bash
php artisan app:test-storage                      # staging  → folder staging/
APP_ENV=production php artisan app:test-storage   # production → folder production/
```

Expected for each: `Write, read and delete all worked.`

### B8. Move the staging demo receipts to R2

```bash
php artisan app:reset-demo
```

This reloads the staging demo data, and its sample receipts are now uploaded to the bucket.
Production has no receipts yet, so nothing needs moving there.

### B9. Restart the app

Stop and start `composer dev` / `composer prod` so they pick up the new settings. In the
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
| Changes in `.env` seem ignored | Restart `composer dev` / `composer prod`; run `php artisan config:clear`. |

## Security

- `.env` is never committed. If a password or key ever leaks, revoke it (Google App Passwords
  page / Cloudflare API tokens page) and create a new one.
- The R2 token can only touch this one bucket, so a leaked key cannot reach anything else in your
  Cloudflare account.
