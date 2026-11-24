# 31 — Demo Data at Scale · Requirements

User's request of 2026-11-18: `app:reset-demo` should load the demo accounts and requests in one
go, with far more requests across every stage and office, from extra requester accounts made for
the data only, so the dashboard, reports, queues and their page limits can be seen in use.

## User stories

- **US-31-1** As the team presenting the system, I want one command to fill local or staging with
  months of realistic activity, so that every dashboard tile, report, queue and page has data.

## Acceptance criteria

- **REQ-31-01** `php artisan app:reset-demo` SHALL do the same reset as `app:reset` (spec 23), then
  load the demo accounts and the hand-written sample requests (credentials.md) and the generated
  data below, with no option needed. It SHALL refuse to run in production, changing nothing.
- **REQ-31-02** THE SYSTEM SHALL create generated requester accounts (default 60, `DEMO_REQUESTERS`)
  on `example.com` with the demo password: Filipino names, every college and its programs, both
  sexes and every academic status, each with a complete profile
  and a saved valid ID. They are not listed in credentials.md. Two more registrars,
  `registrar2@example.com` and `registrar3@example.com`, share the evaluation work.
- **REQ-31-03** THE SYSTEM SHALL generate applications (default 400, `DEMO_APPLICATIONS`) submitted
  over the past six months, more of them recent, through the real workflow: older ones mostly
  released (some late), declined or cancelled; recent ones at every stage. They include walk-ins,
  applications of several credentials, Semestral Reports with terms, free Certificates of Grades,
  returns for clarification, office visits for requirements, rejected payments, returns for
  correction between offices and releases to representatives.
- **REQ-31-04** WHATEVER the amount, THE generated data SHALL include a request in every status the
  workflow reaches, and every office SHALL hold documents both received and on their way to it
  (the Office of the Registrar only received ones, REQ-32-09); some requests SHALL be overdue.
- **REQ-31-05** THE generated history SHALL send no notifications or emails, SHALL never be dated
  after the moment it is loaded (or the as-of moment, REQ-31-06), and SHALL come out the same on
  every run.
- **REQ-31-06** `app:reset-demo --as-of="2026-11-20 08:00"` (or `DEMO_AS_OF`) SHALL date the demo
  data as if it were loaded at that moment, so it can be loaded the day before a presentation and
  still be about the day itself. Without it, the moment of loading is used. An as-of that cannot be
  read SHALL stop the command before anything is wiped. *(User's request, 2026-11-19: presentation
  on 2026-11-20.)*
- **REQ-31-07** WHATEVER the amount, THE demo data SHALL include, around the as-of day, credentials
  being processed at an office whose due date is 5, 3 and 1 working days past, the day itself, and
  the next 1 and 2 working days, two of each; and applications submitted and under evaluation that
  day.
