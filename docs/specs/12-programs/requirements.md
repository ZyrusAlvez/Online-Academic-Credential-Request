# 12 — Programs & Strands · Requirements

## User stories

- **US-12-1** As a requester, I want to choose my program or strand from a list for my college, so
  that I don't have to type it and the Registrar reads the same name every time.
- **US-12-2** As the registrar, I want to keep that list myself, so that a new or renamed program
  doesn't need a developer.

## Acceptance criteria

*Since spec 18 (2026-11-07), the Programs page is the Admin's, at `/admin/programs`. Read "the
registrar" as "the Admin" in REQ-12-03..06 (REQ-18-04).*

### Master list
- **REQ-12-01** THE SYSTEM SHALL keep programs and strands in the database, each belonging to one
  college, with a name that is unique within that college.
- **REQ-12-02** WHEN the base data is seeded THE SYSTEM SHALL install the university's published
  programs (the 27 programs of the 2026-11-06 list, see design) without duplicating or overwriting
  what the registrar has changed. *(Reworded 2026-11-15: the list is seeded, not installed by a
  migration on deploy, REQ-23-02.)*
- ~~REQ-12-12~~ *Superseded by REQ-23-01 and 02 (2026-11-15):* the one-time replacement of the
  earlier program list on existing databases is gone; the 27 programs are the seeded list
  (REQ-12-02).
- ~~REQ-12-11~~ *Superseded by REQ-18-11 and REQ-23-02 (2026-11-15):* no staff account is created
  automatically. The Admin creates every staff account, the Basic Education Department's included.

### Registrar
- **REQ-12-03** THE SYSTEM SHALL let the registrar list programs, search them by name and filter
  them by college and status. The Programs page calls a college a "department" (filter "All
  departments", column and form field "Department", validation messages), as the Registrar's
  Office does.
- **REQ-12-04** THE SYSTEM SHALL let the registrar add a program and edit its name or college.
- **REQ-12-05** THE SYSTEM SHALL let the registrar deactivate and reactivate a program instead of
  deleting it; an inactive program SHALL NOT be offered to requesters.
- **REQ-12-06** IF anyone but a registrar opens the Programs page THEN THE SYSTEM SHALL respond 403.

### Requester
- **REQ-12-07** THE SYSTEM SHALL show My Profile's "Program" as a dropdown of the active
  programs of the chosen college, disabled until a college is chosen; choosing another college
  SHALL clear the chosen program.
- **REQ-12-08** THE SYSTEM SHALL accept only an active program of the chosen college.
- **REQ-12-09** WHEN a saved program is no longer on the list THE SYSTEM SHALL keep it on the
  profile and on past requests, and SHALL ask for a listed one the next time the profile is saved.
- **REQ-12-10** WHILE a chosen college has no active programs THE SYSTEM SHALL tell the requester to
  contact the Office of the Registrar.
