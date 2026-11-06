# 14 — Activity Logs · Requirements

The BRD's module 12, "Administration and Audit Trail", asks for system logs, and its controls (§X)
ask for "a complete audit trail … with timestamps and authorized user details". Spec 06 already
keeps a per-request ledger. This spec adds one log of what everyone did across the whole system.

## User stories

- **US-14-1** As the registrar, I want to see what requesters, office staff and registrars did,
  and when, so that I can answer "who did this?" without asking around.
- **US-14-2** As the registrar, I want to search the log by transaction ID (reference no.) or name
  and narrow it by role, kind of action, office and date, so that I can find one action quickly.

## Acceptance criteria

### What is logged
- **REQ-14-01** WHEN any action on a request is recorded in its ledger (spec 06) THE SYSTEM SHALL
  also log it, with the person who did it, the request's reference no. and the office involved.
- **REQ-14-02** WHEN a credential type, route step, office, program, purpose or non-working day is
  added, changed or removed THE SYSTEM SHALL log it, with each changed field's old and new value.
- **REQ-14-03** WHEN an account is created or changed (including activation and deactivation,
  name corrections and a requester's own profile) THE SYSTEM SHALL log it, with each changed
  field's old and new value.
- **REQ-14-04** WHEN a requester uploads a valid ID, or the Registrar verifies or rejects one,
  THE SYSTEM SHALL log it.
- **REQ-14-05** WHEN someone signs in or out, or a sign-in fails, THE SYSTEM SHALL log it; a
  failed sign-in records the email that was typed. A deactivated account SHALL never be logged as
  signed in: the sign-in form refuses it before signing it in (a failed sign-in), and a
  remember-me cookie that briefly signs it in is logged as a refused sign-in.
- **REQ-14-06** THE SYSTEM SHALL record each entry's date and time, the person's name and role as
  they were at that moment, and the IP address it came from.

### Integrity
- **REQ-14-07** THE SYSTEM SHALL keep the log append-only: entries can't be edited or deleted.
- **REQ-14-08** THE SYSTEM SHALL NOT log passwords, remember-me tokens or Google account IDs, not
  even as a changed field; a password change SHALL be logged as having happened, without either
  password.

### Logs page
- **REQ-14-09** THE SYSTEM SHALL show the log to registrars as "Logs" under Administration, newest
  first, 25 entries per page, showing when, who (with role), the action, the transaction ID and
  the office; a changed record SHALL show each field's old and new value.
- **REQ-14-10** THE SYSTEM SHALL let the registrar search the log by reference no., the person's
  name or email, or the action's description.
- **REQ-14-11** THE SYSTEM SHALL let the registrar filter the log by role (Requester, Registrar,
  Department Staff or System), kind of action (Requests, Accounts, Setup, Identity, Sign-ins),
  office, and a date range.
- **REQ-14-12** WHEN an entry belongs to a request THE SYSTEM SHALL link its reference no. to that
  request's page.
- **REQ-14-13** IF anyone but a registrar opens the Logs page THEN THE SYSTEM SHALL respond 403.
- **REQ-14-14** WHEN a deployment migrates THE SYSTEM SHALL copy the request actions already in
  the ledgers into the log, so the history from before this feature is searchable too.
