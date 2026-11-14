# 14 — Activity Logs · Design

## Data

`activity_logs`: `actor_id` (nullable), `actor_name`, `actor_role`, `category`, `action`,
`description`, `reference_no`, `department_id`, `subject_type`/`subject_id`, `changes` (JSON
`{field: [old, new]}`), `ip_address`, `created_at`. Indexed on `created_at`, `(actor_id,
created_at)`, `(category, created_at)` and `reference_no`. See `docs/data-dictionary.md`.

`App\Models\ActivityLog` is append-only, like `RequestEvent`: its `updating` and `deleting` hooks
throw. `category` is the `ActivityCategory` enum (`request`, `account`, `setup`, `identity`,
`sign_in`).

**Why one table, and why a copy of request events.** The page needs one searchable, paginated
list across every kind of action. One table with snapshot columns gives a single indexed query.
A union over `request_events` and a second table would need joins on both sides for every
search. Request events are therefore stored twice. `request_events` stays the source of truth for
the request flow (spec 06), and the log is the audit view. The actor's name and role are copied
in, so an entry still shows who acted under the name they had at the time.

## Writing

- `App\Actions\Audit\RecordActivity::handle()` is the only writer. It defaults the actor to the
  signed-in user and the IP to the current request's, and snapshots the actor's name and role.
- **Requests (REQ-14-01):** `RequestWorkflow::record()` calls it right after creating the ledger
  event, in the same transaction. The action is `request.<event type>`. The description is the
  event's label and summary, plus the remarks.
- **Setup, accounts and IDs (REQ-14-02..04):** the `App\Models\Concerns\LogsActivity` trait hooks
  `created`, `updated` and `deleted` on `CredentialType`, `WorkflowStep`, `Department`, `Program`,
  `Purpose`, `NonWorkingDay`, `User` and `IdentityDocument`.
  - This covers the add/edit screens that save models directly, without changing each screen.
  - Each model names itself (`activityLabel()`) and its category (`activityCategory()`).
  - Changes record dirty fields only. Hidden fields (`password`, `remember_token`, `google_id`)
    and timestamps are dropped. An update with nothing left is not logged (REQ-14-08).
  - Nothing is logged while seeding or migrating, that is, in the console with no signed-in user.
    The demo data and the starter lists stay out of the log.
- **Sign-ins (REQ-14-05):** `App\Listeners\RecordSignInActivity` handles the `Login`, `Logout` and
  `Failed` auth events. Google sign-in goes through `Auth::login`, so it is covered too.

## Reading

- `App\Queries\ActivityLogFilter` follows `RegistrarRequestFilter`. It takes search, role,
  category, office, from and to, and returns entries newest first.
  - Role `system` means no actor: a failed sign-in, or a scheduled job.
  - Dates are whole days in Asia/Manila.
- `Registrar\Logs\Index` (`/registrar/logs`, "Logs" at the end of Administration):
  - The filters are `#[Url]` properties, as on the Accounts page.
  - A Flux table with a "Details" disclosure for changed fields.
  - Authorized by `ActivityLogPolicy::viewAny` (registrars only).
- The migration that creates the table also copies every existing `request_events` row in
  (REQ-14-14). Tests build their own data, so the test runner skips the copy.
