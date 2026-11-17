# 30 — Demo Data at Scale · Design

## Command
`ResetDemo` drops `--requests`: after `app:reset` it always seeds `DemoRequestSeeder`, which
seeds `DemoSeeder` (accounts), the hand-written scenarios, then `DemoBulkSeeder`.

## Shared helpers
`Database\Seeders\Concerns\DrivesDemoWorkflow` (trait) holds what both request seeders use:
- the actors (registrars, cashier, office staff)
- `submit`, `evaluate`, `pay`, `accepted`, `advanceUntil`, `release`
- the clock helpers `at()` and `later()`

`later()` never moves the clock past the real moment the seeding started (REQ-30-05).

## DemoBulkSeeder
- Reproducible: `fake()->seed(…)` before anything random.
- Quiet: `Notification::fake()` while it runs, then the real notifier is put back. The in-app bell
  and the email stay for the hand-written scenarios only.
- Requesters:
  - `config('uphsl.demo.requesters')` accounts, named `first.last.NN@example.com`
  - the colleges are given round-robin first, so every college has requesters
  - programs come from the seeded list; SHS students take STEM
  - about 70 % graduated, 15 % currently enrolled, 15 % former students
  - each saves a PhilSys ID about seven months back
- Coverage set (always): for each status the workflow reaches, two requests; for each of the 15
  offices, one document in transit to it and one received there (a TOR by a student of that
  college for the Dean, a TOR for VPF and the Chancellor, a diploma for the President); plus
  three overdue requests.
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
- Evaluators are spread over the three registrars; each request's evaluator acts on it to the end
  of its evaluation.

## Config
`config/uphsl.php` → `demo.requesters` (`DEMO_REQUESTERS`, 60) and `demo.applications`
(`DEMO_APPLICATIONS`, 300). Tests set them low.
