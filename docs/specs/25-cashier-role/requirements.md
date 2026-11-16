# 25 — The Cashier Account · Requirements

Source: *Proposed Workflow*, section V, step 3: "authorized personnel" confirm the payment
against the official payment record. The BRD doesn't name them; this spec makes them a fifth
account type, **Cashier**.

Decided by the user on 2026-11-17:

- A new Cashier account validates the payments of online requests.
- The Registrar validates the payments of walk-in requests (spec 26).
- The demo data includes a cashier.

This changes:

- REQ-18-01: five roles instead of four.
- REQ-18-03: the Registrar's Payment Validation lists walk-in requests only.
- REQ-18-09: the Admin can also create Cashier accounts.
- REQ-18-12: the demo data also has a cashier.
- REQ-16-03 and REQ-16-17: the queue and the decision for online requests move to the Cashier.

A request is either **online** (made by the requester in their account) or **walk-in** (recorded
by the Registrar at the counter, spec 26). Every request made before this change is online.

## User stories

- **US-25-1** As the cashier, I want a list of the online payments to validate and the same
  decision the registrar had, so that payments are confirmed by the office that receives them.
- **US-25-2** As the registrar, I want only the walk-in payments in my Payment Validation, so
  that I don't act on payments the cashier handles.
- **US-25-3** As the Admin, I want to create cashier accounts.

## Acceptance criteria

### The role
- **REQ-25-01** THE SYSTEM SHALL have a `cashier` role, shown as *Cashier*. Opening `/dashboard`
  or logging in as a cashier SHALL lead to the cashier's Payment Validation (`/cashier/payments`).
- **REQ-25-02** THE cashier's sidebar SHALL show Payment Validation only. The cashier SHALL have
  Settings (password and email notifications) like the other staff.
- **REQ-25-03** IF a cashier opens a registrar, department, records or admin page, or calls one of
  its actions, THEN THE SYSTEM SHALL respond 403. IF anyone but a cashier opens a cashier page,
  THEN THE SYSTEM SHALL respond 403.

### Payment Validation
- **REQ-25-04** THE cashier's Payment Validation SHALL list the **online** requests whose proof of
  payment awaits verification, oldest first, with a "Waiting for the requester" tab for the
  online requests awaiting payment, and the search of REQ-16-25 (tracking number or name).
- **REQ-25-05** THE cashier's working page (`/cashier/payments/{tracking no.}`) SHALL show what
  REQ-16-17 shows (the proof of payment, the payment details and the request's record) and its
  decision: Decline, Return to Requester (remarks required) and Approve (remarks optional). IF the
  request is not an online request awaiting payment verification, THEN THE page SHALL go back to
  the list.
- **REQ-25-06** THE cashier SHALL be able to open the proof of payment of an online request.
- **REQ-25-07** THE Registrar's Payment Validation SHALL list the **walk-in** requests whose payment
  awaits validation only, with no "Waiting for the requester" tab. THE Registrar SHALL NOT
  approve, return or decline the payment of an online request: its registrar page is the
  request's Records page.

### Notifications
- **REQ-25-08** WHEN a requester uploads a proof of payment THE SYSTEM SHALL notify the active
  cashiers ("Payment to verify for …") instead of the registrars. The link SHALL open the
  cashier's working page.

### Accounts and demo
- **REQ-25-09** THE Admin SHALL be able to create a Cashier account and to change an account's
  role to Cashier. A cashier needs no department.
- **REQ-25-10** THE demo data SHALL include a Cashier account, `cashier@example.com`, with the demo
  password `@Test123`.
