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
  indicators: Total requests (received in the period); and Released on time and Overdue, each as
  a count and a percentage of the eligible requests (REQ-07-11). The average processing time is
  per credential (REQ-34-09).
- **REQ-34-02** THE Dashboard SHALL show one office table, sorted by the longest average first.
  For each office it shows: the average working days per document finished in the period, the
  documents done, and the documents pending now. A document forwarded but not yet received is pending at the office that sent it (REQ-17-18). THE
  slowest office SHALL be highlighted. Each pending count SHALL link to Records filtered to that
  office.
- **REQ-34-03** THE Dashboard SHALL show the causes of delay for overdue requests due in the
  period: the count per cause, using the same causes as the Delay Analysis report (REQ-07-18), all
  four causes of REQ-34-10 listed, largest first. Each cause SHALL have an information icon to the
  left of its name whose tooltip states what the cause covers. It SHALL link to that report.
- **REQ-34-04** THE Dashboard SHALL NOT show the status-count tiles, the secondary indicators
  (overdue workload, returns, average time per office, initial evaluation time), the separate
  "Where Requests Are Waiting" and "Most Overdue" lists, a single overall average processing
  time, an oldest-pending column, or helper lines under its headings.
- **REQ-34-09** THE Dashboard SHALL show the processing time by credential. For each credential
  type with a request released in the period, it shows the requests released, their average
  working days from acceptance to release (REQ-07-11's formula, per credential; Proposed Workflow
  XIII.2), and the credential's standard processing days. Longest average first. An average above
  the standard SHALL be marked.
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

### Reports
- **REQ-34-05** Reports SHALL offer exactly four reports: Office Performance (the default),
  Delay Analysis, Transaction Record and Monthly Summary. All four SHALL use the existing filters
  (period, credential, evaluator, office). An unknown report name, such as a removed report's,
  SHALL open Office Performance.
- **REQ-34-06** THE Office Performance report SHALL show, per office and sorted by the longest
  average first: the average working days per document, the documents done, the documents
  returned, the documents pending now, the longest pending in working days, and the on-time
  percentage by the office deadline (REQ-30-09).
- **REQ-34-07** THE Transaction Record SHALL list each request submitted in the period, in order.
  Each row shows: the tracking number, the date requested, the expected release date (due), the
  requester, the program, the credential, the date released, the evaluator, the timeliness, and
  the cause of delay. Timeliness is one of: Yes (released by the due date), Late (released after
  it), Overdue (open past it), Pending, or Declined or Cancelled. The cause of delay is given for
  Late and Overdue requests only, using the Delay Analysis cause (REQ-07-18).
- **REQ-34-08** THE Monthly Summary SHALL show, per month of the period up to today: the requests
  received; the requests due (eligible, REQ-07-11); those released on time, released late, and
  still pending past the due date; the on-time and overdue percentages; and the average processing
  time. The totals SHALL tie: due = on time + late + still pending (pilot consultation §10).
