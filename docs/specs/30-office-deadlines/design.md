# 30 — Office Deadlines · Design

## Data
New migration `2026_11_18_000004_add_office_deadlines.php` (the 16 baseline files are not edited):

- `workflow_steps.allotted_days` unsigned tinyint, default 0, backfilled for existing types with
  the default split (REQ-30-02).
- `request_steps.allotted_days` unsigned tinyint, nullable: copied at acceptance; null on the final
  quality check and on requests accepted before this change.
- `office_visits.due_at` timestamp, nullable: the office deadline (REQ-30-05).

Existing requests keep no office deadlines; they still have the request's due date.

## The split
`CredentialType::splitDays(int $days, int $steps): list<int>` is the one rule (REQ-30-02);
`CredentialType::resplitDays()` writes it to the type's steps with a query update (the action that
caused it is already logged). `CredentialTypeSeeder` creates each route's steps with `splitDays()`,
or with its fixed TOR split (1-2-2-2). Called by
`AddWorkflowStep` and `RemoveWorkflowStep`, and by `CredentialTypeForm::save()` when the processing
period changes. `MoveWorkflowStep` keeps each step's days.

`App\Actions\Workflow\SetAllottedDays` saves the Admin's split (REQ-30-03): each 0–60, total =
processing period, otherwise a validation error on `days`. Each changed step is an Eloquent update,
so the activity log records it.

## Deadline
`RequestWorkflow::acceptOne()` copies `allotted_days` onto the request steps (REQ-30-04).
`RequestWorkflow::receive()` sets `office_visits.due_at = WorkingCalendar::addWorkingDays(now(),
allotted_days)` unless the visit is a correction or the step has none (REQ-30-05, 06).
`OfficeVisit::isOverdue()` = in the office (received) and `due_at` past.

## Screens
- Route page (`Admin\CredentialTypes\Workflow`): each step shows its days; a "Working Days for
  Each Office" form lists the steps with a number input each and the total against the processing
  period.
- Office queues (`livewire/department/queue`): below the credential, "Due Mon, Nov 23" from the
  visit's deadline while received; the Overdue badge when the visit or the request is overdue.

## Reminders
`SendDueReminders` first reminds offices by visit deadline (received visits with `due_at` within one
working day), with `DueReminder` given the visit so the office sees its own deadline. The request
loop then skips the office reminder when the open visit has a deadline (REQ-30-08). Reminder log
kinds stay `office_due_soon` / `office_overdue` keyed by visit id.

## Report
`DepartmentReport` gets an "On time %" column: of the visits closed in the period that had a
deadline, the share whose `COALESCE(done_at, completed_at) <= due_at` (REQ-30-09).
