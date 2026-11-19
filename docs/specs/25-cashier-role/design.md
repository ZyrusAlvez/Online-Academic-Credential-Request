# 25 — The Cashier Account · Design

## Data

- `App\Enums\RequestChannel` (`online`, `walk_in`): how a request was made.
- Migration `2026_11_17_000001_add_channel_to_credential_requests`: `credential_requests.channel`
  (string 20, default `online`, indexed with `status`). Every existing request stays online.
  *(Renamed 2026-11-19 to `request_type`, enum `App\Enums\RequestType`, by migration
  `2026_11_19_000001_rename_channel_to_request_type`; REQ-26-10.)*

## Role

- `Role::Cashier` (`cashier`): label "Cashier", home route `cashier.payments`. Every exhaustive
  `match` on the role gets a Cashier arm (`CredentialRequestPolicy::view`, `RequestActivity::url`,
  `DueReminder::url`).
- Sidebar (`partials/nav.blade.php`): a Cashier group with Payment Validation.
- Settings: the cashier gets the Notifications page (`role:student,registrar,department,cashier`).
- `UserFactory::cashier()`; `DemoSeeder` adds `cashier@example.com`.
- `StaffForm` allows `cashier`; the account dialogs list it.

## Routes

| Route | Component | Notes |
|---|---|---|
| `cashier` | redirect | to `cashier/payments` |
| `cashier/payments` | `Cashier\Payments` | `cashier.payments`; tabs To verify / Waiting for the requester |
| `cashier/payments/{request}` | `Cashier\Requests\Show` | `cashier.payments.show` |

Both under `role:cashier`.

## Who validates

- `CredentialRequestPolicy::validatePayment(User, CredentialRequest)`: a cashier for an online
  request, a registrar for a walk-in one, and only in `payment_verification`.
- `StageQueue::for(RegistrarQueue::Payment, ..., channel: RequestChannel)` filters by channel. The
  registrar's Payment queue passes `WalkIn`; `Cashier\Payments` passes `Online`.
- `RegistrarQueue::forRequest()` returns Payment for walk-in requests only, so an online request
  awaiting payment has no registrar working page: `RegistrarLink::to()` leads to Records.
- `RegistrarQueue::hasWaitingTab()` is Initial Evaluation only (walk-ins never wait for payment).
- `Registrar\Requests\Show::approve/returnRequest/decline` at `payment_verification` authorize
  `validatePayment`.

## Cashier pages

- `Cashier\Payments` renders the shared queue list (`livewire.registrar.queue`) with the Payment
  queue, the cashier's link route and its own title.
- `Cashier\Requests\Show`: `mount` authorizes `validatePayment` (redirects to the list with a toast
  when the request has moved on); `approve`, `returnRequest` and `decline` call
  `RequestWorkflow::verifyPaymentAndAccept`, `rejectPayment` and `decline`. The proof and payment
  details are the partial `partials/payment-review`, shared with the registrar's page.

## Notifications

- `SendRequestNotifications::notifyRegistrars` sends `PaymentSubmitted` to active cashiers;
  `RequestActivity::url` gives cashiers `cashier.payments.show`.
- `PaymentProofPolicy::view` allows a cashier.
