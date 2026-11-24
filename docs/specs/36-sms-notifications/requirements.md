# 36 — SMS Notifications · Requirements

Requesters are told about their request in-app and by email (spec 06). This adds a text message
for the notices that matter most, sent through the cheapest provider for the number: a Philippine
provider for +63 numbers and an international one for the rest (user's decision, 2026-11-24). A
contact number can now be from any country.

## User stories

- **US-36-1** As a requester, I want a text message when my request is received, needs my action,
  is accepted, declined, ready for release or late, so that I don't miss it if I don't read email.
- **US-36-2** As a requester living abroad, I want to give my own country's mobile number.
- **US-36-3** As the Registrar's Office, I want texts sent as cheaply as possible.

## Acceptance criteria

### Contact number
- **REQ-36-01** THE requester's contact number (New Request, Settings → Profile, and the Registrar's
  Walk-in Request) SHALL be entered as a country, Philippines (+63) by default, and a number. THE
  SYSTEM SHALL accept only a valid mobile number for that country and SHALL store it in
  international (E.164) form, e.g. `+639171234567`. *(Replaces the 11-digit rule of REQ-01-16.)*
- **REQ-36-02** THE existing 11-digit Philippine numbers (`09…`) of accounts and requests SHALL be
  converted to that form (`+639…`); anything else is left as it is.
- **REQ-36-03** THE SYSTEM SHALL show a contact number in international format, e.g.
  `+63 917 123 4567`. *(No test: wording.)*
- A representative's contact number (REQ-11) is unchanged: they are not texted.

### Who is texted, and when
- **REQ-36-04** THE SYSTEM SHALL text the requester, at their saved contact number, when the request
  is received, accepted (with the expected release date), declined or ready for release, on every
  [ACTION REQUIRED] notice (REQ-06-18), and when it becomes overdue (REQ-06-13). No other
  notification is texted, and staff and admins are never texted.
- **REQ-36-05** THE SYSTEM SHALL let requesters turn text messages on or off in Settings →
  Notifications; they are on for every new and existing account. WHILE they are off THE SYSTEM
  SHALL still text [ACTION REQUIRED] notices, like email (REQ-06-16).
- **REQ-36-06** A requester with no contact number SHALL NOT be texted; nothing else changes.

### Sending
- **REQ-36-07** THE SYSTEM SHALL send a text to a +63 number through the Philippine provider
  (PhilSMS) and any other number through the international provider (Telnyx). WHILE a provider's
  keys are not set THE SYSTEM SHALL write its texts to the log instead, like mail (so local and
  staging cost nothing until keys are set).
- **REQ-36-08** A text SHALL name the reference number, say what happened, and link to the
  requester's request page, kept short to fit in as few SMS parts as possible. *(No test: wording.)*
- **REQ-36-09** A text that fails to send (the provider refuses it or cannot be reached) SHALL be
  logged and SHALL NOT stop the in-app notification or the email (REQ-06-10).
- **REQ-36-10** `php artisan app:test-sms <number>` SHALL send one text right away, through the
  provider that number would use, to check an environment's settings.
