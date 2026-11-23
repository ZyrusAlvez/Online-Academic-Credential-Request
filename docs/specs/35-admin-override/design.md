# 35 — Admin Override · Design

## Schema

A new migration, `2026_11_23_000001_add_override_to_request_events`, adds to `request_events`
(after `remarks`):

| Column | Type | Null | Description |
|---|---|---|---|
| override_reason | varchar(500) | Yes | Set on every entry an Admin override wrote: why |
| recorded_at | timestamp | Yes | Set on the same entries: when it was recorded |

On those entries `created_at` is when the step happened (REQ-35-09), so every reader of the ledger
(history, queues ordered by when a status was entered, reports) keeps using `created_at`. Both
columns are null on every other entry. `RequestEvent` gains them in `$fillable`, `recorded_at` cast
to a datetime. The ledger stays append-only.

## Workflow (`RequestWorkflow`)

The override runs the existing transitions; it adds no way of changing a status.

- `asOverride(string $reason, CarbonInterface $at): static` returns a **copy** of the service that
  records as an override; the shared instance never changes.
- `now()` (private) is the override's time on such a copy and the current time otherwise. Every
  time the service stamps (visits, steps, acceptance, due dates, ready, release, payment review,
  ledger entries) comes from it. Only `mirrorToSiblings()`'s `updated_at` stays the real time.
- `record()` sets the entry's `created_at` from `now()`; on an override it also sets
  `override_reason` and `recorded_at` (the real time).
- `lock()`, on an override, refuses a time earlier than the request's latest ledger entry
  (REQ-35-08), inside the row lock.
- `unlockEvaluation(CredentialRequest, User)`: only on an override; the request must be under
  evaluation; it goes back to `submitted` with an `evaluation_unlocked` entry. `evaluated_by` stays
  until the next `startEvaluation()` replaces it, as after a resubmission; the checks stay.

`RequestEventType::EvaluationUnlocked` (`evaluation_unlocked`) is internal (REQ-06-02); its summary
is "Evaluation unlocked; back in Initial Evaluation" and its history icon `lock-open`.

## The step (`App\Enums\OverrideStep`)

`OverrideStep::for(CredentialRequest)` is the step a credential waits for, or null:

| Request | Open visit | Step | Runs |
|---|---|---|---|
| `under_evaluation` | | Unlock | `unlockEvaluation` |
| `payment_verification` | | ValidatePayment | `verifyPaymentAndAccept` (online) / `verifyWalkInPayment` (walk-in) |
| `ready_for_release` | | Release | `release` |
| processing | in transit | Receive | `acceptReceipt` |
| processing | at the final check (`isAtFinalCheck`) | FinalCheck | `approveFinalCheck` |
| processing | forwardable (`isForwardable`) | Forward | `forwardDocument` |
| processing | received, not at the Office of the Registrar | Approve | `completeVisit` |
| anything else | | — | |

Before acceptance the application is acted on through its first credential (REQ-27-03), so the
step of any credential of the application is its first credential's:
`CredentialRequest::actingCredential()` gives the credential acted on.

`label(CredentialRequest)` names the step with its office ("Accept receipt at VP Finance",
"Forward to Chancellor", "Approve at College of Engineering…", "Approve the correction at …");
`button()` is its button.

## Action (`App\Actions\Override\RecordOverride`)

`handle(User $admin, CredentialRequest $request, OverrideStep $step, string $reason,
CarbonInterface $happenedAt, array $details = [], ?int $seenEventId = null)`:

1. Reads the credential afresh and takes its acting credential.
2. Refuses (`InvalidTransition`) unless `OverrideStep::for()` is still `$step` and, when given,
   the latest ledger entry (`CredentialRequest::latestEventId()`) is still the one the Admin saw
   when the dialog opened (REQ-35-13).
3. Refuses a time in the future or before the minute of the latest ledger entry; a time in that
   minute takes the entry's time (REQ-35-08).
4. Runs the step on `$workflow->asOverride($reason, $at)`. `$details` carries the walk-in receipt
   number and amount, and the release recipient.

## Policy

`CredentialRequestPolicy::override`: the Admin, while `OverrideStep::for()` is not null. The
office, forward, payment and release policies are unchanged: they stay their roles'.

## Records page

`Registrar\Records\Show` uses a new concern, `OverridesSteps`:

- `openOverride()` authorizes, keeps the step offered and the latest ledger entry (locked
  properties, REQ-35-13), sets *When it happened* to now (`Y-m-d\TH:i`, the app timezone) and
  shows the `override` modal.
- `recordOverride(RecordOverride)` authorizes, validates (reason required, up to 500; the time a
  valid date, not after now; the walk-in receipt number and amount; the recipient), runs the action
  through `attempt()` (a toast, or the refusal's message) and closes the modal on success. After
  a refusal the dialog offers the request's step now, keeping what was typed, or closes when
  there is none.
- Anyone but the Admin gets 403 (REQ-35-01). The Admin, on a credential with no step left (it
  moved on meanwhile), gets a message instead.
- The credential acted for is the tab shown.

`partials/override-dialog.blade.php`: the modal, headed by the step's label, with *When it
happened* (`datetime-local`), the step's own fields, *Reason*, and Back / the step's button. The
record shows the Override button on the "Where it is now" line under `@can('override', $request)`.

The release recipient (Claimed by: the owner, a verified representative, someone else with their
name) moves from `Registrar\Requests\Show` into a concern, `ChoosesRecipient`, and a partial,
`partials/release-recipient.blade.php`, shared by the Release page and the override.

## History and log

- `x-request-timeline`, with `show-actors` (staff pages only), shows under an override's entry:
  "Admin override · recorded <time>: <reason>". The requester's page doesn't pass `show-actors`.
- `RecordRequestEvent` starts the activity log's description with "Admin override (happened
  <time>) – " and ends it with " · Reason: <reason>", so the log's 500-character limit can only
  cut the reason (the ledger keeps it whole); the log entry keeps the real time.

## Notifications (`SendRequestNotifications`)

- `notifyOutgoing()` notifies when department staff, or the Admin through an override, finished
  the office's task and the paper still waits in Outgoing, its visit done (REQ-32-11, REQ-35-12).
  The visit's state replaces the next-step test: a paper followed by the same office or by the
  final check, or forwarded in the same step (the Admin's Forward at the Office of the Registrar),
  has its visit completed. A registrar's own task still never notifies.
- `evaluation_unlocked` goes to every active registrar (`RequestRegistrars::all()`): "<tracking
  no.> is back in Initial Evaluation". It is internal, so the requester isn't told.
