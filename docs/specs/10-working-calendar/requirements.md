# 10 — Working Calendar & Due Dates · Requirements

Source: *Proposed Workflow*, sections VI-C (time per office in working time), VII (processing
periods in working days) and X (automatic due-date computation, working calendar management).

## User stories

- **US-10-1** As the registrar, I want due dates to skip Sundays, holidays and office closures,
  so that requesters and offices get a fair processing period.
- **US-10-2** As the registrar, I want to maintain the university's non-working days, so that
  the calendar matches the official proclamations and closures.
- **US-10-3** As the registrar, I want time at each office counted in working days, so that
  performance reports compare offices fairly.

## Acceptance criteria

### Working days
- **REQ-10-01** THE SYSTEM SHALL count as working days the days of the configured work week
  (default Monday to Saturday) that are not in the working calendar.
- **REQ-10-02** WHEN a number of working days is added to a date THE SYSTEM SHALL return the end
  of the last of that many working days after that date (the start date itself is not counted).
- **REQ-10-03** WHEN the working days between two moments are counted THE SYSTEM SHALL count the
  working days after the first moment's date up to and including the second moment's date
  (the same day counts as 0).

### Due dates
- **REQ-10-04** WHEN the processing clock of a request starts THE SYSTEM SHALL set its due date
  to the end of the working day that comes the credential type's processing days after the start.
- **REQ-10-05** WHEN the working calendar changes THE SYSTEM SHALL use it for due dates set from
  then on and SHALL NOT change due dates already set.

### Calendar management
- **REQ-10-06** THE SYSTEM SHALL let the registrar list the non-working days of a year, and add,
  edit and delete a non-working day (date, name, kind: regular holiday, special non-working day
  or office closure); a date can appear only once.
- **REQ-10-07** THE SYSTEM SHALL seed the fixed-date Philippine regular holidays and special
  non-working days, and Holy Week, for 2026 and 2027, without creating duplicates when run again.
- **REQ-10-08** IF a user who is not a registrar opens the working calendar THEN THE SYSTEM SHALL
  refuse access.
