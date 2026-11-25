# 36 — SMS Notifications · Requirements

Requesters are told about their request in-app and by email (spec 06). This adds a text message
for the notices that ask them to act, sent through the cheapest provider for the number: a Philippine
provider for +63 numbers and an international one for the rest (user's decision, 2026-11-24). A
contact number can now be from any country.

## User stories

- **US-36-1** As a requester, online or walk-in, I want a text message when I need to do something
  about my request, so that I don't miss it if I don't read email.
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
- **REQ-36-04** THE SYSTEM SHALL text the requester every [ACTION REQUIRED] notice (REQ-06-18),
  and nothing else: an online requester at their account's contact number, whatever their email
  setting (like the email, REQ-06-16). Staff and admins are never texted. *(User's decision,
  2026-11-24: texts are for action required only, to keep the cost down.)*
- **REQ-36-05** A walk-in request not linked to an account yet (REQ-26-09) SHALL have its
  [ACTION REQUIRED] notices texted to the request's own contact number; it still gets no in-app
  notification or email. Once linked, it is texted like any online request. *(User's decision,
  2026-11-24.)*
- **REQ-36-06** A requester with no contact number SHALL NOT be texted; nothing else changes.

### Sending
- **REQ-36-07** THE SYSTEM SHALL send a text to a +63 number through the Philippine provider
  (IPROG SMS) and any other number through the international provider (Telnyx). WHILE a provider's
  keys are not set THE SYSTEM SHALL write its texts to the log instead, like mail (so local and
  staging cost nothing until keys are set). *(IPROG instead of PhilSMS: user's decision, 2026-11-25,
  since PhilSMS no longer registers sender names for academic projects; IPROG accepts capstone
  teams.)*
- **REQ-36-08** A text SHALL start with "[ACTION REQUIRED]", name the reference number and say what
  happened; an online requester's also links to their request page. It SHALL be kept short to fit in as few SMS parts as possible. *(No test: wording.)*
- **REQ-36-09** A text that fails to send (the provider refuses it or cannot be reached) SHALL be
  logged and SHALL NOT stop the in-app notification or the email (REQ-06-10).
- **REQ-36-11** Outside production THE SYSTEM SHALL send every text to the one number set as
  `SMS_REDIRECT_TO` instead of the requester's, and only write it to the log when none is set, since
  demo requesters have made-up numbers that may belong to real people (like mail, which is
  redirected locally).
- **REQ-36-10** THE test suite SHALL check the provider keys set in the environment's `.env`
  against the providers themselves, without sending a text (IPROG's credit balance, Telnyx's balance
  and messaging profile), and SHALL skip a provider whose keys are not set, so it costs nothing and
  runs without keys on CI. Every other SMS test fakes the providers. *(User's decision, 2026-11-25:
  replaces `app:test-sms`, which sent a paid text.)*
