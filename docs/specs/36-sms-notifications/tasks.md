# 36 — SMS Notifications · Tasks

- [ ] Spec 36; notes on REQ-06-16 and REQ-15-03; data dictionary
- [ ] `propaganistas/laravel-phone`; `PhoneNumbers` helper
- [ ] Migration: `09…` numbers to `+639…`
- [ ] Profile and walk-in forms: country + number, stored as E.164; views show the international format
- [ ] Demo seeders and factories make E.164 numbers
- [ ] `SmsSender`, PhilSMS / Telnyx / log senders, `SmsRouter`, `SmsChannel`, config and `.env.example`
- [ ] `RequestActivity` texts [ACTION REQUIRED] notices; `User::wantsSms()`; `WalkInActionText` for unlinked walk-ins; `SMS_REDIRECT_TO`
- [ ] `app:test-sms`; deployment guide
- [ ] Pest tests REQ-36-01, 02, 04..07, 09..11
- [ ] `composer qa`
