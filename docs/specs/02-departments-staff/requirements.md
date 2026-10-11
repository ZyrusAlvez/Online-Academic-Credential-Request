# 02 — Departments & Staff Accounts · Requirements

## User stories

- **US-02-1** As the registrar, I want to maintain the list of colleges and offices, because
  the real signing workflow is still being finalised and offices may be added later.
- **US-02-2** As the registrar, I want to create accounts for department staff and other
  registrar staff, so that each office can sign requests in the system.
- **US-02-3** As the registrar, I want to deactivate any account, so that people who leave
  or misuse the system lose access without losing their history.

## Acceptance criteria

### Departments
- **REQ-02-01** THE SYSTEM SHALL seed the 15 UPHSL departments from the overview (11 colleges,
  4 offices) with a name, short code, type and workflow role description; re-seeding SHALL NOT
  create duplicates.
- **REQ-02-02** THE SYSTEM SHALL let the registrar list departments with search by name/code and
  a filter by type (college / office) and status.
- **REQ-02-03** THE SYSTEM SHALL let the registrar create a department with name, code, type and
  optional description; name and code SHALL be unique (code is stored upper-case).
- **REQ-02-04** THE SYSTEM SHALL let the registrar edit a department's name, code, type and
  description.
- **REQ-02-05** THE SYSTEM SHALL let the registrar deactivate and reactivate a department.
  Departments SHALL NOT be deletable, so that past requests keep their history.
- **REQ-02-06** WHILE a department is inactive THE SYSTEM SHALL exclude it from choices for new
  requests and new workflow steps (enforced in specs 03 and 04).

### Staff accounts
- **REQ-02-07** THE SYSTEM SHALL let the registrar list all accounts with search by name/email
  and filters by role and status, showing each staff member's department.
- **REQ-02-08** THE SYSTEM SHALL let the registrar create a staff account with first, middle
  (optional), last name, email, role (`department` or `registrar`), department (required for
  `department`, must be active) and an initial password; the account SHALL be pre-verified.
- **REQ-02-09** THE SYSTEM SHALL let the registrar edit a staff account's names, email, role and
  department, and optionally reset its password.
- **REQ-02-10** THE SYSTEM SHALL let the registrar deactivate and reactivate any account
  (student or staff), except their own.
- **REQ-02-11** THE SYSTEM SHALL NOT let the registrar create or convert accounts into
  `student`; student accounts are self-registered and only their status can be changed.
- **REQ-02-12** IF a non-registrar opens a department or account management page or action THEN
  THE SYSTEM SHALL respond 403.
