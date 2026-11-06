# 02 — Offices & Staff Accounts · Requirements

Source: *Proposed Workflow*, sections VI–VII (offices and signatories).

## User stories

- **US-02-1** As the registrar, I want to maintain the list of colleges and offices that process
  credentials, so that routes can name them and offices can be added later.
- **US-02-2** As the registrar, I want to create accounts for department staff and other
  registrar staff, so that each office can sign requests in the system.
- **US-02-3** As the registrar, I want to deactivate any account, so that people who leave
  or misuse the system lose access without losing their history.
- **US-02-4** As the registrar, I want to change an existing account's role, so that someone who
  joins or leaves an office keeps one account instead of registering again.

## Acceptance criteria

### Departments
- **REQ-02-01** THE SYSTEM SHALL seed the 15 offices from the overview: the 11 colleges (each
  acting as the Dean) and the Office of the Registrar, VP Finance, Chancellor and President, with
  a name, short code, type and role description; re-seeding SHALL NOT create duplicates.
- **REQ-02-14** WHEN a deployment migrates THE SYSTEM SHALL replace the earlier college list with
  the 11 colleges (list of 2026-11-06, see design):
  - each earlier college becomes its successor under the new name and code, keeping its requests
    and requesters. Requesters of BS Architecture move to Engineering and Architecture;
  - the College of Law is removed, or only deactivated while a request, requester or log entry
    still names it;
  - the earlier colleges' staff accounts are removed. An account that has already acted (a
    request, an office visit, a log entry) is deactivated instead, so the history stays;
  - in local and staging, each college keeps or gets one demo staff account,
    `<code>@example.com`, with the demo password. In production the registrar creates the new
    staff accounts.
- **REQ-02-13** WHEN the offices are seeded THE SYSTEM SHALL deactivate the retired Library,
  Cashier / Finance Office and Student Affairs offices (their clearance is now an evaluation
  requirement), keeping them for the history of earlier requests.
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
  and filters by role and status, showing each staff member's department. The name search
  matches the first name, the last name or "first last" together (e.g. "juan dela"), as the
  request and ID searches do.
- **REQ-02-08** THE SYSTEM SHALL let the registrar create a staff account with first, middle
  (optional), last name, email, role (`department` or `registrar`), department (required for
  `department`, must be active) and an initial password; the account SHALL be pre-verified.
- **REQ-02-09** THE SYSTEM SHALL let the registrar edit a staff account's names, email and
  department, and optionally reset its password. A role is changed with Change role (REQ-02-15).
- **REQ-02-10** THE SYSTEM SHALL let the registrar deactivate and reactivate any account
  (student or staff), except their own.
- **REQ-02-11** THE SYSTEM SHALL NOT let the registrar create `student` accounts or edit a
  student's profile; student accounts are self-registered. A student's role can still be changed
  (REQ-02-15).
- **REQ-02-15** THE SYSTEM SHALL let the registrar change the role of any existing account
  (`student`, `department` or `registrar`), except their own, from the account's Change role
  action. The `department` role needs an active department; any other role clears it. The
  account keeps its name, email, password and history, and the new role applies from its next
  page (it then starts on the new role's home page).
- **REQ-02-16** IF the account is a student with a request that is not yet released, declined
  or cancelled THEN THE SYSTEM SHALL refuse the role change and say that the open requests must
  be finished or cancelled first, because only a student can follow up on their requests.
- **REQ-02-17** WHEN an account's role changes THE SYSTEM SHALL log it in the activity log as
  "Changed the role of <name> (<email>)" with the old and new role (and department).
- **REQ-02-12** IF a non-registrar opens a department or account management page or action THEN
  THE SYSTEM SHALL respond 403.
