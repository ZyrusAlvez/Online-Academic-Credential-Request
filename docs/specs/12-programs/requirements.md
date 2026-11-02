# 12 — Programs & Strands · Requirements

## User stories

- **US-12-1** As a requester, I want to choose my program or strand from a list for my college, so
  that I don't have to type it and the Registrar reads the same name every time.
- **US-12-2** As the registrar, I want to keep that list myself, so that a new or renamed program
  doesn't need a developer.

## Acceptance criteria

### Master list
- **REQ-12-01** THE SYSTEM SHALL keep programs and strands in the database, each belonging to one
  college, with a name that is unique within that college.
- **REQ-12-02** WHEN a deployment migrates THE SYSTEM SHALL install the university's published
  programs without duplicating or overwriting what the registrar has changed.

### Registrar
- **REQ-12-03** THE SYSTEM SHALL let the registrar list programs, search them by name and filter
  them by college and status.
- **REQ-12-04** THE SYSTEM SHALL let the registrar add a program and edit its name or college.
- **REQ-12-05** THE SYSTEM SHALL let the registrar deactivate and reactivate a program instead of
  deleting it; an inactive program SHALL NOT be offered to requesters.
- **REQ-12-06** IF anyone but a registrar opens the Programs page THEN THE SYSTEM SHALL respond 403.

### Requester
- **REQ-12-07** THE SYSTEM SHALL show My Profile's "Program or strand" as a dropdown of the active
  programs of the chosen college, disabled until a college is chosen; choosing another college
  SHALL clear the chosen program.
- **REQ-12-08** THE SYSTEM SHALL accept only an active program of the chosen college.
- **REQ-12-09** WHEN a saved program is no longer on the list THE SYSTEM SHALL keep it on the
  profile and on past requests, and SHALL ask for a listed one the next time the profile is saved.
- **REQ-12-10** WHILE a chosen college has no active programs THE SYSTEM SHALL tell the requester to
  contact the Office of the Registrar.
