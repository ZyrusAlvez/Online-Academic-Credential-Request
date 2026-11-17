# 28 — Requester's Sex and Maiden Name, Renamed Colleges · Requirements

Client's changes of 2026-11-18: the requester's sex (and, for a woman, her maiden name, under which
her records may be kept), clearer status and payment wording, and the college list as the
Registrar's Office now names it.

## User stories

- **US-28-1** As the registrar, I want to know a requester's sex and, for a woman, her maiden name,
  so that I can find records kept under the name she had as a student.
- **US-28-2** As a requester, I want the choices on the form to use the school's own words, so that
  I pick the right one.

## Acceptance criteria

### Wording
- **REQ-28-01** THE landing page's second step SHALL read "Pay the fee online and upload your proof of
  payment." (not "receipt").
- **REQ-28-02** THE SYSTEM SHALL label the academic statuses "Currently Enrolled", "Graduated" and
  "Former student" (was "Current student", "Graduate", "Former student").

### Sex and maiden name
- **REQ-28-03** THE Personal Information section of New Request, Settings → Profile and Walk-in
  Request SHALL ask for the requester's sex, Male or Female, and SHALL require it.
- **REQ-28-04** WHILE Female is chosen THE SYSTEM SHALL show an optional "Maiden name" field; WHEN
  Male is chosen THE SYSTEM SHALL hide it and SHALL NOT save a maiden name.
- **REQ-28-05** THE summary's Personal Information SHALL show the sex and, for a woman, the maiden
  name ("Not provided" when blank, REQ-15-19).
- **REQ-28-06** THE SYSTEM SHALL keep each request's own copy of the sex and maiden name (as
  REQ-15-08) and show them to the registrar with the request's identity details; a request's details
  SHALL show the maiden name when there is one.
- **REQ-28-07** THE sex and maiden name SHALL belong to the identity part verified once (spec 21):
  changing either sends the Personal Information back for verification.

### Colleges and programs
- **REQ-28-08** WHEN the base data is seeded THE SYSTEM SHALL name the colleges "College of Business
  and Accountancy" (code CBA), "College of Criminology" (COC) and "Senior High School" (SHS), and
  SHALL NOT seed a Basic Education Department: Elementary and Junior High are under the Principal's
  Office, not this system.
- **REQ-28-09** WHEN the base data is seeded THE SYSTEM SHALL list Senior High School's strands as its
  programs: for now only "Science, Technology, Engineering and Mathematics (STEM)", until the
  Registrar gives the full list (the Admin adds the others on the Programs page).
