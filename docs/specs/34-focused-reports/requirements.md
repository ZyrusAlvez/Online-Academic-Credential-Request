# 34 — Focused Reports and Dashboard · Requirements

User's request of 2026-11-19: the Admin's Dashboard and Reports showed too much. Both now answer the
BRD's main question (Proposed Workflow I, IX-D, IX-E, XIII.3): **which office takes the longest,
what is pending where, and why it is late**. Pilot consultation §10 (2026-11-17) adds the overdue
percentage, totals that tie, and a per-transaction record.

User's decisions the same day: Reports keeps four reports: Office Performance, Delay Analysis,
Transaction Record and Monthly Summary. The Daily, Weekly, Evaluator, Signatory, Credential
Tracking, Customer Compliance and Release reports are removed. This deliberately departs from the
ten reports in Proposed Workflow IX-B. The Dashboard keeps only the indicators, the office
table and the causes of delay.

User's decision, later on 2026-11-19: the dashboard drops the office table's oldest-pending column
and the single Average processing time tile. One average over every credential mixes 1-day and
10-day documents, so it is replaced by the average per credential type, as Proposed Workflow
XIII.2 defines the indicator ("average number of working days required to process each type of
credential"). The Monthly Summary keeps its overall average, and Office Performance keeps its
longest-pending column.

## User stories

- **US-34-1** As the Admin, I want to see at a glance which office holds documents the longest,
  so that I can follow up with the bottleneck.
- **US-34-2** As the Admin, I want a short list of reports about office time, delays and each
  transaction, so that the reports replace the evaluators' logbook and support the ISO quality
  objective.

## Acceptance criteria

### Dashboard
- **REQ-34-01** THE Dashboard SHALL show, for a chosen period (this month by default), three
  indicators: Total requests (received in the period); and Ready on time and Overdue, each as
  a count and a percentage of the eligible requests (REQ-07-11). The average processing time is
  per credential (REQ-34-09). Each tile SHALL open the requests behind it: Total requests the
  Transaction Record; Ready on time the Transaction Record of the requests due in the period and
  ready on time; Overdue the Delay Analysis report.
- **REQ-34-02** THE Dashboard SHALL show one office table, sorted by the longest average first.
  For each office it shows: the average working days per document finished in the period, the
  documents done, and the documents pending (columns Office, Avg per document, Done, Pending). A
  document forwarded but not yet received is pending at the office that sent it (REQ-17-18). No
  office is highlighted. The office's name, average, Done and Pending SHALL open Office Documents
  (REQ-34-12) for that office and period: all of them, the finished, the done and the pending ones.
- **REQ-34-03** THE Dashboard SHALL show the causes of delay for overdue requests due in the
  period: the count per cause, using the same causes as the Delay Analysis report (REQ-07-18), all
  four causes of REQ-34-10 listed, largest first. Each cause SHALL have an information icon to the
  left of its name whose tooltip states what the cause covers. It SHALL link to that report, and
  a cause with requests SHALL open Delay Analysis filtered to that cause.
- **REQ-34-04** THE Dashboard SHALL NOT show the status-count tiles, the secondary indicators
  (overdue workload, returns, average time per office, initial evaluation time), the separate
  "Where Requests Are Waiting" and "Most Overdue" lists, a single overall average processing
  time, an oldest-pending column, a Reports button, or helper lines under its headings.
- **REQ-34-09** THE Dashboard SHALL show the processing time by credential. For each credential
  type with a request made ready for release in the period, it shows how many, their average
  working days from acceptance to ready for release (REQ-07-11's formula, per credential; Proposed Workflow
  XIII.2), and the credential's standard processing days. Longest average first. An average above
  the standard SHALL be marked. The credential's name, count and average SHALL open the
  Transaction Record of that credential's requests made ready in the period.
- **REQ-34-10** THE cause of a delay SHALL be one of four, decided by the office holding the
  document (or, once it has left the offices, the office where it stayed longest):
  - Registrar / evaluation: the Office of the Registrar (verifying records, preparing the
    credential, the final quality check), or no office visit yet;
  - Pending signature: the Dean, the Chancellor or the President;
  - VP Finance documentary stamp: the Office of the VP for Finance;
  - Document correction: a document an office returned, being corrected.

  *(User's decision, 2026-11-20: the clock runs from payment and acceptance to ready for release,
  and nothing in that time is asked of the requester, so customer compliance is not a cause; the
  Dean's step is a signature like the Chancellor's and President's; a correction is internal.
  Reasons recorded before REQ-24-09 still show as text, but no longer set the cause.)*
- **REQ-34-11** THE processing clock SHALL stop when the credential is marked ready for release:
  a request is on time when it was ready for release by its due date, late when it was ready
  after it, and pending until then. Processing time is counted from acceptance to ready for
  release. When the requester claims it does not count. *(User's decision, 2026-11-20: the
  BRD's "released" means ready for release; the claim date is outside the offices' control.)*

### Reports
- **REQ-34-05** The Reports list SHALL offer four reports: Office Performance (the default),
  Delay Analysis, Transaction Record and Monthly Summary. Office Documents (REQ-34-12) is opened
  from the dashboard's office table and is not in the list. An unknown report name, such as a
  removed report's, SHALL open Office Performance.
- **REQ-34-06** THE Office Performance report SHALL show, per office and sorted by the longest
  average first: the average working days per document, the documents done, the documents
  returned, the documents pending now, the longest pending in working days, and the on-time
  percentage by the office deadline (REQ-30-09).
- **REQ-34-07** THE Transaction Record SHALL list each request submitted in the period, in order.
  Each row shows: the tracking number, the date requested, the expected release date (due), the
  requester, the program, the credential, the date ready for release, the date released, the
  processing time in working days, the evaluator, the timeliness, and the cause of delay. Its
  Show filter (REQ-34-13) narrows it to the requests due in the period and ready on time, or to
  those made ready for release in the period. Timeliness (REQ-34-11) is one of: Yes (ready
  by the due date), Late (ready after it), Overdue (not ready and past it), Pending, or Declined
  or Cancelled. The cause of delay is given for
  Late and Overdue requests only, using the Delay Analysis cause (REQ-07-18).
- **REQ-34-08** THE Monthly Summary SHALL show, per month of the period up to today: the requests
  received; the requests due (eligible, REQ-07-11); those ready on time, ready late, and
  still pending past the due date (REQ-34-11); the on-time and overdue percentages; and the average processing
  time. The totals SHALL tie: due = on time + late + still pending (pilot consultation §10).
- **REQ-34-12** THE Office Documents report SHALL list one row per document at an office: those
  finished (done or returned) in the period, the same ones Office Performance counts, and those the
  office holds now (in transit = at the sender, REQ-17-18). Each row shows the tracking number,
  the credential, the office, received, finished, the working days (up to now when pending) and
  the result: Done, Returned or Pending. Its Show filter narrows it to the finished, done,
  returned or pending ones.
- **REQ-34-13** THE Reports page SHALL show only the filters of the chosen report, and a filter
  the report does not show SHALL NOT narrow it:

  | Report | Filters |
  |---|---|
  | Office Performance | period, credential |
  | Delay Analysis | period, credential, office, cause |
  | Transaction Record | period, credential, evaluator, office, show (pilot §10) |
  | Monthly Summary | period, credential, evaluator, office |
  | Office Documents | period, credential, office, show |

  *(User's decision, 2026-11-20.)*
