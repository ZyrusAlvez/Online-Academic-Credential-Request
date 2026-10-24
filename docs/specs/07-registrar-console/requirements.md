# 07 — Performance Dashboard & Reports · Requirements

Source: *Proposed Workflow*, section IX (performance analysis and management reporting: dashboard
indicators, the ten reports, the KPI formulas, delay and bottleneck analysis) and XIII (performance
measures).

## User stories

- **US-07-1** As the registrar, I want an overview of the workload and of on-time performance, so
  that I can spot delays and report to management.
- **US-07-2** As the registrar, I want to see which offices hold requests the longest and why
  requests are delayed, so that I can take corrective action.
- **US-07-3** As the registrar, I want the client's reports, filtered and exportable, so that I can
  monitor daily operations and support management review.

## Acceptance criteria

### Monitoring
- **REQ-07-01** *(Replaced by REQ-10-04: due dates count working days from the working calendar.)*
- **REQ-07-02** WHILE a request is being processed past its effective due date (REQ-05-12) THE
  SYSTEM SHALL flag it as overdue in the registrar's request list and request page, and offer an
  "Overdue" filter.
- **REQ-07-03** THE SYSTEM SHALL show the registrar counts of requests needing the Registrar,
  pending compliance, awaiting payment, being processed and ready for release, and overdue requests.
- **REQ-07-05** THE SYSTEM SHALL show, per office, how many documents it currently holds
  (forwarded or received), largest first, each linking to the request list filtered to that office.
- **REQ-07-06** THE SYSTEM SHALL list the most overdue requests (up to 5) on the dashboard.
- **REQ-07-07** THE SYSTEM SHALL let the registrar filter the request list by the office currently
  holding the document.
- **REQ-07-08** THE SYSTEM SHALL let the registrar download the filtered request list as CSV
  (tracking number, credential, requester, student number, college, status, current office,
  submitted, accepted, due, adjusted due, released, processing working days).

### Performance indicators
- **REQ-07-04** THE SYSTEM SHALL show the registrar, for a chosen period (this month by default):
  the requests received; the requests released on time and overdue, each as a count and a
  percentage of the eligible requests; the average processing time; the open overdue workload; the
  documents returned for correction; and the average initial evaluation time.
- **REQ-07-11** THE SYSTEM SHALL compute the indicators with the client's formulas: eligible
  requests are accepted requests (not cancelled or declined) whose effective due date falls in the
  period and not after today; on-time % = released on or before the due date ÷ eligible × 100;
  overdue % = not released by the due date ÷ eligible × 100; average processing time = working days
  from acceptance to release ÷ requests released in the period; average office time = working days
  across completed office visits ÷ completed visits. Requests still within their period SHALL NOT
  count as overdue; open requests past their due date are reported separately as overdue workload.
- **REQ-07-12** THE SYSTEM SHALL show the average working days per office for visits completed in
  the period, longest first (bottleneck analysis).

### Reports
- **REQ-07-13** THE SYSTEM SHALL offer the registrar the client's ten reports — daily monitoring,
  weekly performance, monthly performance, evaluator performance, department performance,
  signatory monitoring, credential tracking, delay analysis, customer compliance and release —
  filtered by date range, credential type, evaluator and office.
- **REQ-07-14** THE periodic reports SHALL show, per day, week or month: requests received,
  accepted, released, released on time, overdue, pending at the end of the period, and the average
  processing time.
- **REQ-07-15** THE evaluator performance report SHALL show, per evaluator: requests evaluated,
  still pending evaluation, sent for compliance, average initial evaluation time and workload.
- **REQ-07-16** THE department performance report SHALL show, per office: documents received and
  released, returns, documents pending now and the average working days per completed visit; THE
  signatory monitoring report SHALL list the documents pending at each office and for how long.
- **REQ-07-17** THE credential tracking report SHALL show each request's complete routing history
  (office, received, released, working days, returns).
- **REQ-07-18** THE delay analysis report SHALL show, for overdue requests: tracking number,
  credential, acceptance and due dates, the office holding it, the stage, days overdue, the number
  of returns, the recorded cause (the latest delay reason, otherwise inferred from the stage) and
  the corrective action, distinguishing the holding office from the cause.
- **REQ-07-19** THE customer compliance report SHALL list requests waiting for missing
  requirements, clarification or payment, with what is missing and since when; THE release report
  SHALL list credentials ready for release, released (and to whom) and unclaimed (ready for more
  than 10 working days).
- **REQ-07-20** THE SYSTEM SHALL let the registrar download each report as CSV with its filters,
  safe against spreadsheet formula injection, and SHALL refuse reports to other roles.

### Demo data & documentation
- **REQ-07-09** THE SYSTEM SHALL provide a demo seeder (refused in production) that creates one
  account per office and sample requests in every status.
- **REQ-07-10** THE SYSTEM SHALL include a README describing setup, demo accounts, architecture
  and the spec index.
