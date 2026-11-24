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

*Since spec 18 (2026-11-07), the Dashboard and Reports are the Admin's, at `/admin` and
`/admin/reports`. There, read "the registrar" as "the Admin" (REQ-18-04, 08). Records and its
office filter (REQ-07-07) serve both the Registrar and the Admin, at `/records` (REQ-18-06).*

### Monitoring
- **REQ-07-01** *(Replaced by REQ-10-04: due dates count working days from the working calendar.)*
- **REQ-07-02** WHILE a request is being processed past its effective due date (REQ-05-12) THE
  SYSTEM SHALL flag it as overdue in the registrar's Records and In Progress lists and request
  page, and offer an "Overdue" filter in Records (REQ-16-04, 07).
- ~~REQ-07-03~~ *Superseded by REQ-34-04 (2026-11-19):* the dashboard has no status counts; the
  statuses are Records filters (REQ-18-08).
- ~~REQ-07-05~~ *Superseded by REQ-34-02:* the documents each office holds are the office
  table's "Pending now".
- ~~REQ-07-06~~ *Superseded by REQ-34-04:* no most-overdue list; overdue requests are in the Delay
  Analysis report.
- **REQ-07-07** THE SYSTEM SHALL let the registrar filter Records and the In Progress queue by the
  office currently holding the document.
- ~~REQ-07-08~~ *Superseded by REQ-16-08:* there is no CSV download.

### Performance indicators
- ~~REQ-07-04~~ *Superseded by REQ-34-01 (2026-11-19):* four indicators only.
- **REQ-07-11** THE SYSTEM SHALL compute the indicators with the client's formulas: eligible
  requests are accepted requests (not cancelled or declined) whose effective due date falls in the
  period and not after today; on-time % = ready for release on or before the due date ÷ eligible
  × 100; overdue % = not ready for release by the due date ÷ eligible × 100; average processing
  time = working days from acceptance to ready for release ÷ requests made ready in the period
  (REQ-34-11: the BRD's "released" is read as ready for release); average office time = working days
  across completed office visits ÷ completed visits. Requests still within their period SHALL NOT
  count as overdue; open requests past their due date are listed in the Delay Analysis report
  (REQ-07-18) and counted as "still pending" in the Monthly Summary (REQ-34-08).
- ~~REQ-07-12~~ *Superseded by REQ-34-02:* the office table, longest first.

### Reports
- ~~REQ-07-13~~ *Superseded by REQ-34-05 (2026-11-19, user's decision against Proposed Workflow
  IX-B):* four reports instead of ten.
- ~~REQ-07-14~~ *Superseded by REQ-34-08:* the Monthly Summary; no daily or weekly report.
- ~~REQ-07-15~~ *Restored as REQ-34-15 (2026-11-20):* the Evaluator Performance report.
- ~~REQ-07-16~~ *Superseded by REQ-34-06:* the Office Performance report; no signatory report.
- ~~REQ-07-17~~ *Removed by REQ-34-05:* a request's routing history is on its record (REQ-17-14).
- **REQ-07-18** THE delay analysis report SHALL show, for overdue requests: tracking number,
  credential, acceptance and due dates, the office holding it, the stage, days overdue, the number
  of returns, the cause (one of the four of REQ-34-10), and any reason and corrective action
  recorded before REQ-24-09, distinguishing the holding office from the cause.
- ~~REQ-07-19~~ *Removed by REQ-34-05:* no compliance or release report; those requests are
  Records filters.
- **REQ-07-20** THE SYSTEM SHALL refuse reports, and their CSV export (REQ-34-16), to other roles.

### Demo data & documentation
- **REQ-07-09** THE SYSTEM SHALL provide a demo seeder (refused in production) that creates one
  account per office, a demo registrar and the demo requesters, and a second demo seeder that adds
  sample requests in every status still in use (all but `returned_for_customer_action`, which
  spec 16 retired). `app:reset-demo` loads the accounts only unless given `--requests`
  (REQ-23-07, 08; 2026-11-15). Every demo account SHALL use the
  password `@Test123`, which meets the password rules (REQ-01-03). *(User's decision, 2026-11-07:
  demo logins follow the same password rules as everyone else. The earlier `password` did not.)*
- **REQ-07-10** THE SYSTEM SHALL include a README describing setup, demo accounts, architecture
  and the spec index.
