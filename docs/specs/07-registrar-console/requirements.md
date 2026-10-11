# 07 — Registrar Console & Monitoring · Requirements

## User stories

- **US-07-1** As the registrar, I want an overview of all requests, so that I can monitor the
  workload and spot delays.
- **US-07-2** As the registrar, I want to see which offices are holding requests the longest,
  so that I can follow up with them.
- **US-07-3** As the registrar, I want to export the request list for reports.

## Acceptance criteria

### Due dates & overdue
- **REQ-07-01** WHEN a request is routed THE SYSTEM SHALL set its due date to the routing date
  plus the credential type's processing days, counting weekdays only.
- **REQ-07-02** WHILE a request is `in_progress` past its due date THE SYSTEM SHALL flag it as
  overdue in the registrar's request list and request page, and offer an "Overdue" filter.

### Dashboard
- **REQ-07-03** THE SYSTEM SHALL show the registrar counts of open requests by status, requests
  submitted and released in the current month, and overdue requests.
- **REQ-07-04** THE SYSTEM SHALL show the average turnaround (routing to ready-for-release, in
  days) of requests that became ready in the last 30 days, or "—" when there are none.
- **REQ-07-05** THE SYSTEM SHALL show, per office, how many in-progress requests are currently
  waiting there (for signature or on hold), largest first, each linking to the request list
  filtered to that office.
- **REQ-07-06** THE SYSTEM SHALL list the most overdue requests (up to 5) on the dashboard.

### Request list
- **REQ-07-07** THE SYSTEM SHALL let the registrar filter the request list by the office currently
  holding the request.
- **REQ-07-08** THE SYSTEM SHALL let the registrar download the filtered request list as CSV
  (reference, credential, student, student number, college, status, current office, submitted,
  due, released).

### Demo data & documentation
- **REQ-07-09** THE SYSTEM SHALL provide a demo seeder (refused in production) that creates one
  account per role per office and sample requests in every status.
- **REQ-07-10** THE SYSTEM SHALL include a README describing setup, demo accounts, architecture
  and the spec index.
