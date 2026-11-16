# 26 — Walk-in Requests · Design

## Data

Migration `2026_11_17_000002_add_walk_in_to_credential_requests`:

- `credential_requests.user_id` becomes nullable (a walk-in request has no account until linked).
- `credential_requests.requester_email` (string 255, nullable, indexed with `user_id`): the walk-in
  client's email, used to link the request.
- `credential_requests.recorded_by` (nullable FK to users): the registrar who recorded it.
- `payment_proofs.path`, `original_name`, `mime_type`, `size` become nullable: a walk-in payment
  has no file. `payment_proofs.official_receipt_no` holds its receipt number.

`RequestEventType::WalkInRecorded` and `LinkedToAccount`.

## Recording

- Route `registrar/walk-in` → `App\Livewire\Registrar\WalkIn` (`registrar.walk-in`), first in the
  Registrar's sidebar.
- Form objects: `CredentialRequestForm` (section 1) and `WalkInRequesterForm`, which extends
  `RequesterProfileForm` with `email` and without the ID and the consent. `attested` (bool) is the
  evaluator's confirmation.
- Views: section 1 moves to `partials/credential-choice`, shared with New Request.
  `partials/requester-profile-fields` takes `withId` (default true) and `withEmail` (default
  false); `partials/requester-profile-summary` takes `withId`.
- `App\Actions\WalkIn\RecordWalkInRequest::handle(User $registrar, array $data, array $details)`
  calls `RequestWorkflow::submitWalkIn()`, which saves the request (channel `walk_in`, the
  snapshot, `requester_email`, `recorded_by`, `evaluated_by`, ID verified, requirements complete),
  links it to a verified requester account with that email, records `WalkInRecorded`, and either
  leaves it at `payment_verification` or accepts it (free credential).

## Walk-in payment

- `RequestWorkflow::verifyWalkInPayment($request, $registrar, $receiptNo, $amount)`: creates a
  payment row (no file, `official_receipt_no`, `amount`, `paid_on` today, status verified,
  uploaded and reviewed by the registrar), records `PaymentVerified`, then `accept()`.
- `Registrar\Requests\Show`: at `payment_verification` a walk-in request shows the
  `walk_in_payment` panel (`receiptNo`, `amountPaid`, `validatePayment()`), Decline only.
- `x-payment-proof-list` shows "Official receipt no. …" instead of a file link when a row has no
  file; `PaymentProofController` answers 404 for one.

## Linking

- `App\Actions\Requests\ClaimWalkInRequests::handle(User $user)`: for a verified requester, sets
  `user_id` on walk-in requests with no account and the same email (lowercase), records
  `LinkedToAccount`, and fills an empty profile from the newest one.
- Called by `App\Listeners\ClaimWalkInRequestsOnVerified` (the `Verified` event, fired by the
  code and by `SignInWithGoogle`) and by `submitWalkIn` for an existing account.

## No owner

- `CredentialRequest::student()` may be null. `SendRequestNotifications`, `SendDueReminders`,
  `RequestActivity` and the record views use the request's `requester_name` and
  `requesterEmail()` (the account's email, else `requester_email`).
