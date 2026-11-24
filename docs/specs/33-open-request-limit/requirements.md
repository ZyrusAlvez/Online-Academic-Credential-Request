# 33 — Open Request Limit · Requirements

User's request of 2026-11-19: a limit of 5 requests per account. User's decisions the same day:
the limit counts **open** requests (not Released, Declined or Cancelled), each credential of a
multi-credential application counts as one, and only requests the requester submits online count;
walk-ins encoded by the Registrar (spec 26) never count and are never blocked.

## User stories

- **US-33-1** As the Registrar's Office, I want each requester account to have at most five open
  requests at a time, so that one account cannot flood the queues.

## Acceptance criteria

- **REQ-33-01** THE SYSTEM SHALL count a requester's open requests as their online requests
  (one per credential) whose status is not Released, Declined or Cancelled. Walk-in requests,
  including those linked to the account by email (REQ-26), SHALL NOT count.
- **REQ-33-02** WHEN a requester reviews or submits New Request AND their open requests plus the
  credentials in the form would exceed 5, THE SYSTEM SHALL refuse it with an error on the first
  credential, saving nothing.
- **REQ-33-03** THE submission check SHALL hold under concurrent submissions by the same account:
  two simultaneous submissions SHALL NOT together exceed the limit.
- **REQ-33-04** New Request SHALL NOT warn about the limit in advance: the message shows only when
  the requester reviews or submits beyond it (REQ-33-02). "Add another credential" SHALL be offered
  only while the form holds fewer credentials than the requester has room for. *(Advance notice
  removed 2026-11-19, user's decision.)*
- **REQ-33-05** Edit Request (resubmitting a returned request, spec 20) and the Registrar's
  Walk-in Request SHALL NOT be limited.
