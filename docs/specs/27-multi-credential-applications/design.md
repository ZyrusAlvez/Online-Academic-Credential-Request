# 27 — Several Credentials in One Application · Design

## Data

- New table `request_applications`: `id`, `reference_no` (unique), `user_id` (nullable FK),
  `channel`, `submitted_at`, timestamps. Model `App\Models\RequestApplication`
  (`credentialRequests()`, ordered by `item_no`; `lead()`).
- `credential_requests.application_id` (FK) and `item_no` (unsigned small int, 1 = the first). Unique
  (`application_id`, `item_no`).
- Migration `2026_11_18_000001_create_request_applications`: creates the table and gives each
  existing request its own application, with the same `reference_no`, `user_id`, `channel` and
  `submitted_at`, and `item_no` 1.

## The lead credential

The first credential (`item_no` 1) is the **lead**. Every action before acceptance runs on it with
the existing workflow methods, so the checklist, required items, proof of payment,
representative and ledger entries stay on one row.

- `RequestWorkflow::lock()` refuses a non-lead credential while the application is before
  acceptance ("Act on the application's first credential").
- `RequestWorkflow::transition()`: when the lead moves to another status before acceptance, or to
  `declined` or `cancelled` from one, the other credentials get the same status and the same
  pre-acceptance fields (evaluator, ID review, decline and cancel reasons). They get no ledger
  entry, so notifications are sent once (REQ-27-05).
- `RequestWorkflow::accept()`: after accepting the lead, accepts each other credential the same
  way (route snapshot, due date, first hand-off, `Accepted` entry).
- The amount due on the lead is the total of the application's fees.
- `submit()` and `submitWalkIn()` take one credential, as before, or a list. They create the
  application, then one credential row per item with the same snapshot, and return the lead.
- `CredentialRequest::isLead()`, `application()`, `siblings()`.
- `StageQueue`: Initial Evaluation and Payment Validation list leads only. `RegistrarLink::to()`
  and the policies send a non-lead credential before acceptance to its lead.
- `ClaimWalkInRequests` also sets the application's `user_id`.
