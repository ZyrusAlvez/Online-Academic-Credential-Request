# 31 — Demo Data at Scale · Design

## Command
`ResetDemo` drops `--requests`. After `app:reset` it always seeds `DemoRequestSeeder`, which
seeds, in order:
1. `DemoSeeder` (the accounts)
2. the hand-written scenarios
3. `DemoBulkSeeder`

## Shared helpers
`Database\Seeders\Concerns\DrivesDemoWorkflow` (trait) holds what both request seeders use:
- the actors (registrars, cashier, office staff)
- `submit`, `evaluate`, `pay`, `accepted`, `advanceUntil`, `release`
- the clock helpers `at()` and `later()`

`later()` never moves the clock past the real moment the seeding started (REQ-31-05).

## DemoBulkSeeder
- Reproducible: `DemoRequestSeeder` calls `fake()->seed(…)` before anything random.
- Quiet: `Notification::fake()` while it runs, then the real notifier is put back. The in-app bell
  and the email stay for the hand-written scenarios only.
- Requesters:
  - `config('uphsl.demo.requesters')` accounts, named `first.last.NN@example.com`
  - the colleges are given round-robin first, so every college has requesters
  - programs come from the seeded list
  - about 70 % graduated, 15 % currently enrolled, 15 % former students
  - each saves a PhilSys ID about seven months back
- Coverage set (always), on a TOR so every stage applies:
  - two requests in each status the workflow reaches
  - for each of the 15 offices, two documents on their way to it and two received there (a TOR by
    a student of that college for the Dean, a TOR for VPF and the Chancellor, a diploma for the
    President)
  - three overdue requests
- Random fill: `config('uphsl.demo.applications')` applications.
  - **Submission date:** 0–180 days ago, skewed to recent (`180 · u^1.7`).
  - **Outcome by age:**
    - over 30 days: mostly released
    - 10–30 days: released, ready, in progress or final check
    - under 10 days: spread over the pre-acceptance stages and in progress
  - **Credentials:** weighted; TOR the most common. 12 % carry two credentials and 10 % are
    walk-ins.
  - **Along the way:** 10 % are returned for clarification and resubmitted, 8 % need an office
    visit, 5 % have a payment rejected, 8 % are returned for correction at an office, 15 % of
    releases go to a representative, and a quarter of the releases are slowed at one office so
    they come out late.
- Evaluators are spread over the three registrars.

At the defaults this is about 550 requests; it loads in about a minute and a half locally.

## Config
`config/uphsl.php` → `demo.requesters` (`DEMO_REQUESTERS`, 60) and `demo.applications`
(`DEMO_APPLICATIONS`, 400). `phpunit.xml` sets both to 0 (the coverage set only).
