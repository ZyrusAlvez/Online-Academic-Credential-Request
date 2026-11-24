# 30 — Office Deadlines · Requirements

User's decision of 2026-11-18: each office on a credential's route gets its own deadline, counted
from the day it receives the document, by dividing the credential's processing period among its
offices. The BRD only sets the request's due date and measures time per office (VI-C); office
deadlines are an addition. The request's due date stays the promise to the requester and is not
changed by anything here.

## User stories

- **US-30-1** As an office, I want my own deadline for each document I receive, so that I know
  how long I have and am not blamed for another office's delay.
- **US-30-2** As the Admin, I want the processing period split among the route's offices
  automatically, and to adjust the split, because the offices' work is not equal.
- **US-30-3** As the registrar, I want to see which office finished its part on time.

## Acceptance criteria

### Allotted days
- **REQ-30-01** THE SYSTEM SHALL give each route step of a credential type a number of allotted
  working days, and the steps' days SHALL add up to the credential's processing period.
- **REQ-30-02** WHEN a credential type's processing period or its number of route steps changes,
  or it is seeded, THE SYSTEM SHALL split the period automatically: each step gets the period
  divided by the number of steps (rounded down), and the days left over go one at a time to the
  steps in route order (e.g. 7 days over 4 offices → 2, 2, 2, 1; 6 over 3 → 2, 2, 2). Moving a
  step keeps its days. Exception: the two TOR types are seeded with Registrar 1, Dean 2, VP for
  Finance 2, Chancellor 2 *(user's decision, 2026-11-20)*.
- **REQ-30-03** THE route page SHALL let the Admin change each step's days (0 to 60) and SHALL
  refuse a set whose total is not the processing period.
- **REQ-30-04** WHEN a request is accepted THE SYSTEM SHALL copy each step's allotted days onto the
  request's route, so later changes to the credential type do not affect it. The final quality
  check has no allotted days.

### Office deadline
- **REQ-30-05** WHEN an office accepts receipt of a document for a route step or a corrected
  document sent back to it THE SYSTEM SHALL record the office's deadline: the end of the working
  day that is the step's allotted working days after the receipt date (as REQ-10-02). Each visit
  has its own deadline; a document received again gets a new one.
- **REQ-30-06** A document at an office for correction, or at the final quality check, SHALL have
  no office deadline; the request's due date applies.
- **REQ-30-07** THE office queues SHALL show, for each document in the office, its office deadline
  and "Overdue" once the deadline or the request's due date has passed.

### Reminders and reports
- **REQ-30-08** WHEN a document in an office has an office deadline THE daily reminder SHALL remind
  that office's staff by the office deadline (due within one working day, then overdue), once per
  visit each; documents without one keep the request's due date (REQ-06-11). Registrar and
  requester reminders keep the request's due date.
- **REQ-30-09** THE department performance report SHALL show, per office, the percentage of
  documents with an office deadline whose task was done by that deadline.

### Records
- **REQ-30-10** THE Records list SHALL mark each credential being processed with:
  - "Office overdue" (amber) WHILE the office holding it has received it and its office deadline
    has passed;
  - "Request overdue" (red) WHILE the request's due date has passed (REQ-05-12; shown as
    "Overdue" before this change).

  WHEN both apply, both SHALL show, "Office overdue" first. Neither is a status: both are worked
  out from the stored dates when the list is shown, and the request keeps its status. The status
  filter SHALL offer "Request overdue (count)" (the former "Overdue") and "Office overdue (count)",
  which lists the requests whose paper is overdue at its office, whether or not the request is also
  overdue, the earliest due date first. Elsewhere "Overdue" keeps its wording. *(User's decision,
  2026-11-18: with In Progress removed (REQ-32-10), Records is where the registrar sees a paper
  late at an office.)*
