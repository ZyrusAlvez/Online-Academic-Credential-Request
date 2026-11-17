# 29 — Credential List Changes and Per-Type Options · Requirements

Client's changes of 2026-11-18: Certification – General removed, Copy of Grades and Transfer
Certificate renamed, Semestral Report of Grades added (₱100, with the terms and academic year), and
the Certificate of Grades free for graduates. The user asked for the new behaviours to be options the
Admin sets per credential type, like the receiving-school switch, rather than fixed in code.

## User stories

- **US-29-1** As the Admin, I want to turn on per credential type whether it asks for the terms and
  academic year, whether its fee is per term and which requesters get it free, so that new rules
  of this kind need no developer.
- **US-29-2** As a requester, I want to say which terms and academic year I need, and to see what I
  will pay, so that I get the right document at the right fee.

## Acceptance criteria

### Master list
- **REQ-29-01** WHEN the base data is seeded THE SYSTEM SHALL NOT seed "Certification – General",
  and SHALL name "Certificate of Grades" (was Copy of Grades, free for graduates) and "Certificate
  of Transfer" (was Transfer Certificate).
- **REQ-29-02** WHEN the base data is seeded THE SYSTEM SHALL add "Semestral Report of Grades": ₱100
  per term, 6 working days, Registrar → VP Finance → Chancellor, asking for the terms and academic
  year. *(The fee per term is our reading of how registrars usually issue it, one report per
  semester; the Registrar to confirm. The Admin can switch it to a flat fee.)*

### Admin options
- **REQ-29-03** THE SYSTEM SHALL let the Admin set on each credential type: "Asks for the term and
  academic year", "Fee is per term" and "Free for" (any of Currently Enrolled, Graduated, Former
  student); the last two only apply while the type requires payment.

### Requesting
- **REQ-29-04** WHILE a chosen credential asks for the term and academic year THE New Request and
  Walk-in Request forms SHALL show the terms (1st Semester, 2nd Semester, Summer) as checkboxes and
  an academic year, SHALL require at least one term and the year, and the request SHALL keep both.
- **REQ-29-05** WHEN a credential's fee is per term THE SYSTEM SHALL charge its fee once per ticked
  term, on the summary, the request and the application's amount due.
- **REQ-29-06** THE SYSTEM SHALL allow a credential that asks for the academic year more than once in
  one application, once per academic year; any other credential stays once per application
  (REQ-27-01).
- **REQ-29-07** WHEN the requester's status is one the credential is free for THE SYSTEM SHALL charge
  nothing for it, on New Request, Walk-in Request and Edit Request; an application with nothing to
  pay skips payment.
- **REQ-29-08** THE SYSTEM SHALL show a request's terms and academic year with its details, to the
  requester and to the offices, and the credential's details card SHALL show a per-term fee as
  "per term" and whom it is free for.
