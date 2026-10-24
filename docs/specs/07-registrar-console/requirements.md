# 07 — Registrar Console & Monitoring · Requirements

*Phase 6 rewrites this spec into the performance dashboard and reports of the client's workflow;
until then these criteria describe the console as adapted to the new request lifecycle.*

## User stories

- **US-07-1** As the registrar, I want an overview of all requests, so that I can monitor the
  workload and spot delays.
- **US-07-2** As the registrar, I want to see which offices are holding requests the longest,
  so that I can follow up with them.
- **US-07-3** As the registrar, I want to export the request list for reports.

## Acceptance criteria

### Due dates & overdue
- **REQ-07-01** *(Replaced by REQ-10-04: due dates count working days from the working calendar.)*
- **REQ-07-02** WHILE a request is being processed past its effective due date (REQ-05-12) THE
  SYSTEM SHALL flag it as overdue in the registrar's request list and request page, and offer an
  "Overdue" filter.

### Dashboard
- **REQ-07-03** THE SYSTEM SHALL show the registrar counts of requests needing the Registrar,
  pending compliance, awaiting payment, being processed and ready for release, requests submitted
  and released in the current month, and overdue requests.
- **REQ-07-04** THE SYSTEM SHALL show the average processing time (acceptance to release, in
  working days) of requests released in the last 30 days, or "—" when there are none.
- **REQ-07-05** THE SYSTEM SHALL show, per office, how many documents it currently holds
  (forwarded or received), largest first, each linking to the request list filtered to that office.
- **REQ-07-06** THE SYSTEM SHALL list the most overdue requests (up to 5) on the dashboard.

### Request list
- **REQ-07-07** THE SYSTEM SHALL let the registrar filter the request list by the office currently
  holding the document.
- **REQ-07-08** THE SYSTEM SHALL let the registrar download the filtered request list as CSV
  (tracking number, credential, requester, student number, college, status, current office,
  submitted, accepted, due, adjusted due, released, processing working days).

### Demo data & documentation
- **REQ-07-09** THE SYSTEM SHALL provide a demo seeder (refused in production) that creates one
  account per role per office and sample requests in every status.
- **REQ-07-10** THE SYSTEM SHALL include a README describing setup, demo accounts, architecture
  and the spec index.
