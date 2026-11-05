# 13 — Request Purposes · Requirements

## User stories

- **US-13-1** As a requester, I want to choose the purpose of my request from a list, so that I
  don't have to word it myself and the Registrar reads the same purpose every time.
- **US-13-2** As the registrar, I want to keep that list myself, so that a new or reworded purpose
  doesn't need a developer.

## Acceptance criteria

### Master list
- **REQ-13-01** THE SYSTEM SHALL keep request purposes in the database, each with a unique name.
- **REQ-13-02** WHEN a deployment migrates THE SYSTEM SHALL install a starter list of purposes
  without duplicating or overwriting what the registrar has changed.

### Registrar
- **REQ-13-03** THE SYSTEM SHALL let the registrar list purposes, search them by name and filter
  them by status.
- **REQ-13-04** THE SYSTEM SHALL let the registrar add a purpose and edit its name.
- **REQ-13-05** THE SYSTEM SHALL let the registrar deactivate and reactivate a purpose instead of
  deleting it; an inactive purpose SHALL NOT be offered to requesters.
- **REQ-13-06** IF anyone but a registrar opens the Purposes page THEN THE SYSTEM SHALL respond 403.

### Requester
- **REQ-13-07** THE SYSTEM SHALL show New Request's "Purpose" as a dropdown of the active purposes
  in alphabetical order.
- **REQ-13-08** THE SYSTEM SHALL accept only an active purpose, and SHALL store its name on the
  request.
- **REQ-13-09** WHEN a purpose is renamed or deactivated THE SYSTEM SHALL keep the purpose already
  stored on past requests.
- **REQ-13-10** WHILE no purpose is active THE SYSTEM SHALL tell the requester to contact the Office
  of the Registrar.
