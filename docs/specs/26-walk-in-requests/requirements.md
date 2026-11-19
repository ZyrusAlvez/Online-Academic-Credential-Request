# 26 — Walk-in Requests · Requirements

Source: the user's decision of 2026-11-17. The *Proposed Workflow* assumes every request is made
online by its owner. This spec goes beyond it, at the user's request, so that clients who come to
the Office of the Registrar in person can request too:

- The Registrar records the request for the client from a **Walk-in Request** page, the first in
  the Registrar's sidebar. The form is New Request's without the Valid ID (4) and Who Will Transact
  (5) sections.
- The data privacy consent is replaced by the evaluator's confirmation that they validated the
  request's eligibility and the client's personal and academic information.
- Once submitted, the request goes straight to Payment Validation. The Registrar validates
  walk-in payments; the Cashier validates online ones (spec 25).
- A walk-in client can create an account later. Their walk-in requests are linked to it
  automatically, by email, so they can track them.

There is no other setup for walk-in requests.

## User stories

- **US-26-1** As the registrar, I want to record a walk-in client's request on the same form as
  New Request, so that walk-in requests are tracked like online ones.
- **US-26-2** As the registrar, I want to validate a walk-in client's payment with its official
  receipt number, so that the request is accepted for processing at once.
- **US-26-3** As a walk-in client, I want the requests I made at the office to show in my account
  once I create one, so that I can track them.

## Acceptance criteria

### The Walk-in Request page
- **REQ-26-01** THE Registrar's sidebar SHALL show **Walk-in Request** first, before Initial
  Evaluation, leading to `/registrar/walk-in`. IF anyone but the Registrar opens it or calls one of
  its actions, THEN THE SYSTEM SHALL respond 403. The Registrar's home stays Initial Evaluation.
- **REQ-26-02** THE page SHALL show New Request's sections **1. What Do You Need?**, **2. Personal
  Information** and **3. Academic Information**, with the same fields and checks (REQ-04-02,
  REQ-15-03). Personal Information SHALL also ask for the client's **Email** (required). THE page
  SHALL NOT show the Valid ID section, Who Will Transact, or the portal notice.
- **REQ-26-03** WHEN the Registrar chooses Review request THE SYSTEM SHALL check every entry,
  listing what needs attention like New Request (REQ-01-26), and show the summary of sections 1
  to 3. In place of the data privacy consent, the summary SHALL end with the checkbox *"I have
  validated the eligibility of this request and the requester's personal and academic
  information."* Submit SHALL stay disabled until it is ticked, and THE SYSTEM SHALL refuse a
  submission without it.

### Submitting
- **REQ-26-04** WHEN the Registrar submits a walk-in request THE SYSTEM SHALL save it as a
  **walk-in** request, with a tracking number (REQ-04-03), the entries as the request's copy of
  the requester's details and email, and the Registrar as its recorder and evaluator, its valid ID
  verified. It SHALL go straight to `payment_verification`, or be accepted for processing at once
  when the credential is free. The ledger SHALL say "Walk-in request recorded at the Office of the
  Registrar". THE Registrar SHALL then land on the request's Payment Validation page.

### Walk-in payment
- **REQ-26-05** THE Registrar's Payment Validation page for a walk-in request SHALL show the request's
  details, then the amount due, the **Official receipt no.** (required, up to 50 characters) and
  the **Amount paid** (required, more than 0), and the buttons Decline (remarks required) and
  **Validate Payment**. Validate Payment SHALL record the payment, with no file, as verified by the
  Registrar, and accept the request for processing (REQ-04-14). There is no Return to Requester:
  the client is at the counter, so a receipt is sorted out there. THE workflow SHALL refuse to
  return a walk-in payment wherever it is asked, so a walk-in never waits for a payment.
  *(Refusal in the workflow added 2026-11-19, user's decision: no "Waiting for payment" tab on the
  Registrar's Payment Validation.)*
- **REQ-26-06** THE SYSTEM SHALL show a walk-in payment's receipt number and amount wherever payments are
  listed (Records, the requester's page, an office's page), with no file link.

### Linking to an account
- **REQ-26-07** WHEN a requester account's email is verified (by the code, by signing in with Google,
  or after changing the email) THE SYSTEM SHALL link to that account every walk-in request with
  the same email (ignoring case) that has no account yet. Each link SHALL be in the ledger as
  "Linked to the requester's account". IF the account has no contact number or academic
  information yet, THEN THE SYSTEM SHALL fill them in from the newest linked request (the name is
  kept as one line on the request, so the requester gives theirs on Settings → Profile).
- **REQ-26-08** WHEN a walk-in request is recorded for an email that already belongs to a verified
  requester account THE SYSTEM SHALL link it to that account at once. A request is never linked to
  a staff account, and never moved from one account to another.
- **REQ-26-09** A linked walk-in request SHALL be the account's like any other: listed in the
  Request Tracker, opened by its owner only, and notified to them from then on. Before it is
  linked, THE SYSTEM SHALL send no requester notifications for it.

### Showing walk-in requests
- **REQ-26-10** THE registrar's queues, Records and a request's record SHALL mark a walk-in request
  with a "Walk-in" badge, except the Registrar's Payment Validation list, which lists walk-ins only
  (REQ-25-07). Records, the record and the staff emails SHALL show the request's own name and email
  when it has no account. *(Payment Validation exception: user's decision, 2026-11-19.)*
- **REQ-26-11** THE demo data SHALL include walk-in requests: one linked to a demo requester, one not
  linked to any account.
