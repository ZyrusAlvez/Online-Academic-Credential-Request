# 36 — SMS Notifications · Design

## Providers

| Numbers | Provider | Price (2026-11, check the dashboard) | API |
|---|---|---|---|
| +63 | PhilSMS | about ₱0.35 a text, no minimum top-up | `POST https://app.philsms.com/api/v3/sms/send`, Bearer token; JSON `recipient` (`639…`, no `+`), `sender_id` (≤ 11 chars, approved name), `type: plain`, `message`; replies `{"status": "success"\|"error", …}` |
| everything else | Telnyx | pay as you go, rate per destination | `POST https://api.telnyx.com/v2/messages`, Bearer key; JSON `from` (number or alphanumeric sender ID), `to` (E.164), `text`, and `messaging_profile_id` (needed for an alphanumeric sender) |

Either can be swapped later by writing another `SmsSender`.

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

A new migration `2026_11_24_000001_add_sms_notifications`:

| Table | Column | Type | Null | Default | Description |
|---|---|---|---|---|---|
| users | sms_notifications | tinyint(1) | No | 1 | Requesters: text me about my requests (REQ-36-05) |

It also rewrites `users.contact_number` and `credential_requests.contact_number` values matching
`^09[0-9]{9}$` to `+63` followed by the number without its leading 0 (REQ-36-02). `down()` drops
the column and turns `+639…` back into `09…`.

## Sending

- `App\Support\Sms\SmsSender` (interface): `send(string $to, string $text): void`, throwing
  `SmsFailed` on a refusal.
- `PhilSmsSender`, `TelnyxSender` (Laravel `Http`, 10 s timeout, `throw()` on HTTP errors; PhilSMS
  also fails on `status: error`), `LogSmsSender` (writes to the log).
- `App\Support\Sms\SmsRouter::for(string $to): SmsSender`: `+63` → PhilSMS, else Telnyx; the log
  sender when that provider's key is blank. Bound in the container, so tests fake it with `Http`.
- `App\Notifications\Channels\SmsChannel` (registered as `sms`): reads
  `$notifiable->routeNotificationFor('sms')` (the user's `contact_number`) and
  `$notification->toSms($notifiable)`; catches any failure, logs it as a warning and returns, so the
  other channels still go out (REQ-36-09).
- Config: `services.philsms.{token,sender_id}`, `services.telnyx.{key,from,messaging_profile_id}`;
  `.env.example` lists them.

## Notifications

- `RequestEventType::isTexted(CredentialRequest)`: `Submitted`, `Accepted`, `Declined`,
  `ReadyForRelease`, or `requiresRequesterAction()`.
- `User::wantsSms(bool $actionRequired)`: a requester with a contact number, and
  `sms_notifications` on or `$actionRequired`.
- `RequestActivity::via()` adds `sms` when the event `isTexted()` and the user `wantsSms()`;
  `toSms()` builds e.g. `UPHSL: [ACTION REQUIRED] TOR R-2026-00012: Payment instructions available.
  <link>`. Accepted adds the expected release date.
- `DueReminder::via()` adds `sms` for the overdue requester notice.
- Settings → Notifications (the existing page) gains a second switch for requesters, "Text me about
  my requests", saved by `SetSmsNotifications`. The page and menu say "Notifications".

## Ops

`app:test-sms {number}` (E.164) names the provider and sends one text. Deployment guide: the keys,
per environment.
