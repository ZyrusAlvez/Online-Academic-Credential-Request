# 27 — Several Credentials in One Application · Requirements

Source: the user's decision of 2026-11-17. Until now one request was one credential. A requester,
or the Registrar for a walk-in client (spec 26), can now ask for several credentials in one
**application**.

Each credential has its own route, so the application is shared only **until acceptance**:

- one form;
- one evaluation and office visit, with one decision for the whole application;
- one payment, for the total of the fees.

From acceptance on, each credential is tracked on its own, with its own route, office visits, due
date, final check and release. It is released when it is ready, without waiting for the others.

Decided by the user on 2026-11-17:

- **Tracking numbers.** The application is `rc26-00042`; its credentials are `rc26-00042-1`,
  `rc26-00042-2` and so on.
- **Evaluation.** One decision for the whole application.
- **Release.** Each credential when it is ready.

This changes REQ-04-03, the tracking number.

## User stories

- **US-27-1** As a requester, I want to ask for several credentials in one application, so that I
  fill in my details, am evaluated and pay once.
- **US-27-2** As staff, I want each credential of an application to move through its own offices,
  so that a quick certificate isn't held up by a slow transcript.

## Acceptance criteria

### Applications and tracking numbers
- **REQ-27-01** Every request SHALL belong to an application, with one or more credentials. THE
  application's tracking number SHALL be `rc<2-digit year>-<id>` (REQ-04-03), and each credential's
  the application's number, a hyphen and its position (`rc26-00042-1`). THE same credential SHALL
  NOT be in one application twice.
- **REQ-27-02** Requests made before this change SHALL each be an application of one credential and
  keep their tracking number, so earlier links keep working.

### Until acceptance: one application
- **REQ-27-03** UNTIL acceptance, every credential of an application SHALL have the application's
  status. Evaluation, the office visit, the payment, a decline and a cancellation SHALL apply to
  the whole application: its first credential carries the checklist, the required items, the
  proof of payment, the representative and the ledger entries, and staff and the requester act on
  it only. THE amount due SHALL be the total of the credentials' fees.
- **REQ-27-04** WHEN an application is accepted, THE SYSTEM SHALL accept each of its credentials,
  each with its own route, due date and first hand-off (REQ-04-14). From then on each credential
  SHALL move, be returned, declined at the final check and released on its own.
- **REQ-27-05** Notifications before acceptance SHALL be sent once per application.

### Pages
- **REQ-27-06** THE Request Tracker SHALL list one row per application: its tracking number, its
  credentials, and where each is. The search SHALL match the application's or a credential's
  tracking number, or a credential's name.
- **REQ-27-07** THE requester's request page SHALL show the application's part first: what is needed
  from them, the payment, the representative, Cancel. WHERE the application has more than one
  credential, a tab per credential SHALL follow, each with where it is, its progress, its details
  and its history (the application's entries until acceptance, then its own). A credential's own
  address SHALL open the application on that credential's tab.
- **REQ-27-08** Records SHALL list one row per application, with its credentials. A record SHALL
  have the same tabs as the requester's page (REQ-27-07), each with the credential's record.
- **REQ-27-09** Initial Evaluation, the Registrar's and the Cashier's Payment Validation and the
  requester's payment instructions SHALL list every credential of the application, with its
  purpose and fee, and the total due.

### The form
- **REQ-27-10** New Request and the Registrar's Walk-in Request SHALL let section 1 hold up to 10
  credentials: **Add another credential** adds one, with its own credential, purpose and receiving
  school, and **Remove** takes it out. Each is checked like the first, and a credential chosen
  twice SHALL be refused ("Each credential can be requested only once."). The summary SHALL show
  each credential and, when there are several, each fee and the total. Submitting SHALL create one
  application with all of them. Edit Request keeps the application's credentials: only the first
  can be changed there.
