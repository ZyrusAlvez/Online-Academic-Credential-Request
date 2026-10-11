# 04 — Request Submission, Payment & Registrar Review · Design

## Data

`credential_requests`

| Column | Type | Notes |
|---|---|---|
| reference_no | string(20) unique null | set right after insert: `CR-2026-000042` |
| user_id | FK users restrict | requester |
| credential_type_id | FK restrict | |
| college_id | FK departments restrict | the student's college |
| student_number | string(30) | |
| program | string(150) | |
| student_status | string(10) | `current` / `alumni` (`App\Enums\StudentStatus`) |
| last_school_year | string(9) | `2023-2024` |
| purpose | string(500) | |
| copies | unsigned tinyint | 1–10 |
| requires_payment | boolean | snapshot |
| fee | decimal(10,2) null | snapshot |
| status | string(30) | `App\Enums\RequestStatus`; index (status, created_at) |
| routed_at, ready_at, released_at, cancelled_at | timestamp null | |
| cancel_reason | string(500) null | |
| timestamps | | index (user_id, created_at) |

`request_steps` — the request's own copy of the workflow

| Column | Type | Notes |
|---|---|---|
| credential_request_id | FK restrict | index (credential_request_id, position) |
| position | unsigned smallint | |
| department_id | FK restrict | resolved department |
| instructions | string(255) null | copied from template |
| status | string(20) | `App\Enums\StepStatus`; index (department_id, status) |
| acted_by | FK users null | |
| acted_at | timestamp null | |
| remarks | string(500) null | |

`payment_proofs` — `credential_request_id`, `uploaded_by`, `path` (private `local` disk,
`payment-proofs/{request}/{uuid}.{ext}`), `original_name`, `mime_type`, `size`,
`reference_no` null, `amount` decimal null, `status` (`App\Enums\PaymentStatus`),
`reviewed_by` null, `reviewed_at` null, `remarks` null, timestamps.

`request_events` — append-only ledger: `credential_request_id`, `actor_id` null, `type`
(`App\Enums\RequestEventType`), `from_status` null, `to_status` null, `request_step_id` null,
`department_id` null, `remarks` text null, `created_at`. The `RequestEvent` model throws on
`updating`/`deleting`.

## Code

- `App\Services\RequestWorkflow` — **the only place statuses change.** Each method locks the
  request row (`lockForUpdate`) inside a transaction, asserts the allowed source status
  (`InvalidTransition` exception otherwise), writes the change and a ledger event, then fires
  `App\Events\RequestEventRecorded` after commit (spec 06 notifies from it).
  Spec 04 methods: `submit`, `submitPaymentProof`, `rejectPayment`, `approve` (accepts the
  pending proof when payment is required, then routes), `changeCollege`, `cancel`.
  Separate per-transition Action classes were dropped: they would only wrap these methods.
- `App\Models\CredentialRequest` with `currentStep()` (the step that is `pending`/`on_hold`),
  `RequestStep`, `PaymentProof`, `RequestEvent`.
- `App\Policies\CredentialRequestPolicy` — `view` (owner / registrar / department with a step
  on it), `cancel`, `uploadPayment`, `review`; `PaymentProofPolicy::view`.
- Student pages: `Student\Dashboard` (my requests), `Student\Requests\Create`,
  `Student\Requests\Show` (details, payment panel, status, cancel).
- Registrar pages: `Registrar\Requests\Index` (filterable list; spec 07 extends),
  `Registrar\Requests\Show` (review panel: proofs, change college, approve/accept, reject).
- `PaymentProofController@show` streams the file inline after `authorize('view', $proof)`,
  with `X-Content-Type-Options: nosniff`.
- Livewire file upload: `mimes:jpg,jpeg,png,webp,pdf|max:5120`.

## Routes

| URI | Name |
|---|---|
| /student/requests/new | student.requests.create |
| /student/requests/{credentialRequest} | student.requests.show |
| /registrar/requests | registrar.requests |
| /registrar/requests/{credentialRequest} | registrar.requests.show |
| /payment-proofs/{paymentProof} | payment-proofs.show (auth) |
