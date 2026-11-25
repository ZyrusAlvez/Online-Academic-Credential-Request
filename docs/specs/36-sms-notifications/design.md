# 36 — SMS Notifications · Design

## Providers

| Numbers | Provider | Price (2026-11, check the dashboard) | API |
|---|---|---|---|
| +63 | IPROG SMS | ₱1 a text, about ₱0.78 in packs | `POST https://www.iprogsms.com/api/v1/sms_messages`; JSON `api_token`, `phone_number` (`639…`), `message`; replies `{"status": 200, "message_id": …}` once queued. The sender name (header) is the account's approved one, set on IPROG's dashboard. Key check: `GET /api/v1/account/sms_credits?api_token=…` → `{"status": "success", "data": {"load_balance": …}}` |
| other countries | none: not texted (REQ-36-06) | | |

Another provider can be added later by writing another `SmsSender`. PhilSMS was the first choice;
it no longer registers sender names for academic projects (2026-11-25). Telnyx sent to other
countries until the user dropped international texts (2026-11-25).

## Phone numbers

- `propaganistas/laravel-phone` (libphonenumber) validates and formats numbers. Country names come
  from PHP's `intl` (`Locale::getDisplayRegion`).
- `App\Support\PhoneNumbers`: `countries()` (ISO code → "Philippines (+63)", sorted, PH first),
  `toE164(number, country)`, `split(e164)` → `[country, national number]` for a form, and
  `display(?string)` → international format, or the raw value when it isn't E.164 (old data,
  representatives).
- `RequesterProfileForm` gains `contact_country` (default `PH`). `contact_number` is validated with
  `(new Phone)->countryField('contact_country')->type('mobile')`; `contact_country` with
  `Rule::in` the known countries. Saved (and returned by `WalkInRequesterForm::details()`) as E.164.
  `load()` splits a saved number back into country and national number. The 11-digit
  `PHONE_RULE` stays only for the representative's number.
- The partial `requester-profile-fields` shows a country select beside the number field.
- Views showing a requester's contact number use `PhoneNumbers::display()`.

## Schema

A new migration `2026_11_24_000001_convert_contact_numbers` rewrites `users.contact_number` and
`credential_requests.contact_number` values matching `^09[0-9]{9}$` to `+63` followed by the number
without its leading 0 (REQ-36-02). `down()` turns `+639…` back into `09…`. No column is added.

## Sending

- `App\Support\Sms\SmsSender` (interface): `send(string $to, string $text): void`, throwing
  `SmsFailed` on a refusal.
- `IprogSender` (Laravel `Http`, 10 s timeout; fails on any reply whose `status` isn't 200),
  `LogSmsSender` (writes to the log).
- `App\Support\Sms\SmsRouter::sender(): SmsSender`: IPROG, or the log sender when its key is
  blank. Bound in the container, so tests fake it with `Http`.
- `App\Notifications\Channels\SmsChannel` (used by class name in `via()`): reads
  `$notifiable->routeNotificationFor('sms')` (the user's `contact_number`) and
  `$notification->toSms($notifiable)`; catches any failure, logs it as a warning and returns, so the
  other channels still go out (REQ-36-09).
- Config: `services.iprog.token`,
  `services.sms.{redirect,redirect_to}` (REQ-36-11: `redirect` is on outside production);
  `.env.example` lists them.

## Notifications

- `PhoneNumbers::isTextable(?string)`: a +63 number (REQ-36-06).
- `User::wantsSms(bool $actionRequired)`: a requester with a textable number, when `$actionRequired`.
- `RequestActivity::via()` adds the SMS channel when the requester `wantsSms($this->actionRequired())`;
  `toSms()` builds e.g. `UPHSL: [ACTION REQUIRED] R-2026-00012: Payment instructions available.
  <link>`. Email settings don't change it, so there is no SMS switch.
- `SendRequestNotifications::notifyRequester()`: for a walk-in request with no account, an
  [ACTION REQUIRED] event with a textable number is sent as `WalkInActionText` to an on-demand notifiable routed to the
  request's `contact_number` (REQ-36-05): SMS only, no link (the client has no account).
- `SmsChannel` accepts any notifiable with `routeNotificationFor('sms')` (a `User` or an
  `AnonymousNotifiable`).

## Ops

No command sends a test text (REQ-36-10). `tests/Feature/Notifications/SmsKeysTest.php` reads the
key from the environment (the `.env`, not the config, which `tests/TestCase.php` blanks), lets that
one request through, and checks IPROG's free credit-balance endpoint; it is skipped without a key. Deployment guide: the keys, per environment.
