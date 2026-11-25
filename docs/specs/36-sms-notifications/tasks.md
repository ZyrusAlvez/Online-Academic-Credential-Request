# 36 — SMS Notifications · Tasks

- [x] Spec 36; notes on REQ-06-16 and REQ-15-03; data dictionary
- [x] `propaganistas/laravel-phone`; `PhoneNumbers` helper
- [x] Migration: `09…` numbers to `+639…`
- [x] Profile and walk-in forms: country + number, stored as E.164; views show the international format
- [x] Demo seeders and factories make E.164 numbers
- [x] `SmsSender`, PhilSMS / Telnyx / log senders, `SmsRouter`, `SmsChannel`, config and `.env.example`
- [x] `RequestActivity` texts [ACTION REQUIRED] notices; `User::wantsSms()`; `WalkInActionText` for unlinked walk-ins; `SMS_REDIRECT_TO`
- [x] Deployment guide
- [ ] IPROG SMS instead of PhilSMS (REQ-36-07)
- [ ] Key checks against the providers instead of `app:test-sms` (REQ-36-10)
- [x] Pest tests REQ-36-01, 02, 04..07, 09..11
- [x] `composer qa`
