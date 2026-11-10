# 15 — Identity Checked on the Request · Design

## Data

One migration, `move_identity_check_to_requests`:

- `credential_requests` gains the requester's copy: `requester_name` (150), `contact_number` (20),
  `identity_document_id` (FK `identity_documents`, restrict on delete; nullable only for requests
  from before IDs existed), `identity_status` (`ReviewStatus`: pending, verified, rejected;
  indexed for the registrar's "ID to verify" mark), `identity_reviewed_by` (FK users),
  `identity_reviewed_at`, `identity_remarks` (500).
- Existing requests are filled from their owner: the name as "First Middle Last", the contact
  number, the latest ID and that ID's status (REQ-15-14).
- Dropped: `users.identity_status`, `users.identity_verified_at`, and `identity_documents.status`,
  `reviewed_by`, `reviewed_at`, `remarks`. `identity_documents` stays as each requester's history
  of uploads; the newest is their saved ID. `IdentityStatus` (account level) is deleted.
- Removing the saved ID (REQ-15-18): `identity_documents.removed_at` (nullable timestamp). The saved
  ID is the newest upload with no `removed_at` (`User::latestIdentityDocument()`).
  `App\Actions\Identity\RemoveSavedIdentityDocument` deletes the row and its file when no request
  points at it, and otherwise only sets `removed_at`, since `credential_requests.identity_document_id`
  restricts deletes and the request keeps the ID it was sent with. The Livewire action is
  `removeSavedId()` from `App\Livewire\Concerns\RemovesSavedId`, shared by New Request (and Edit
  Request, which extends it) and Settings → Profile; the button confirms through `wire:confirm`.

`requester_name` keeps the full middle name, not the initial of REQ-01-14, because the registrar
compares it with the ID.

## Workflow

All in `RequestWorkflow`, the one place request state changes:

- `submit(owner, data, ?identity)` copies the owner's name, contact and academic details and the
  given ID (default: their saved ID) onto the request with `identity_status = pending`. It refuses
  an incomplete profile, no ID, or another user's ID. There is no profile gate any more.
- `reviewIdentity(request, registrar, verified, remarks)` locks the request, requires a pending ID
  and a reviewable status (submitted, under evaluation, pending compliance, returned for customer
  action) and a reason to reject, and records `IdentityVerified` / `IdentityRejected`.
- `replaceIdentity(request, owner, document)` takes a newly stored ID while the request's is
  rejected and the request is reviewable, and records `IdentityResubmitted`.
- `completeEvaluation()` checks the request's `identity_status`.

The new ledger types reach people through `SendRequestNotifications` like every other event: the
requester hears about a verified or rejected ID (also mailed, `RequestActivity::MAILED_TO_REQUESTERS`),
registrars about a resubmitted one. The old `IdentityReviewed` notification, `ReviewIdentityDocument`
and `CorrectRequesterName` are removed; activity logs get the review through the ledger (REQ-14-01).

## Screens

- `RequesterProfileForm` is shared by New Request and Settings → Profile. It loses the lock and the
  locked name; the ID is optional when one is saved and required otherwise. Its fields are one
  Blade partial, `partials/requester-profile-fields`, and the summary another,
  `partials/requester-profile-summary`. Every summary section is the `x-summary-section`
  component: a heading and a list of label/value rows (label left, value right from `sm`). New
  Request numbers them like the form (REQ-15-17): `Create::summarySections()` gives sections 1, 2
  and 6, the profile partial 3 to 5.
- `Student\Requests\Create`: form → summary (`reviewing`) → submit. `submit()` runs one transaction:
  save the profile, store a new ID through `SubmitIdentityDocument`, `RequestWorkflow::submit()`,
  declare the representative. If it fails, a stored ID file is deleted.
- Settings → Profile (`/settings/profile`, `role:student`) is the old `Student\Profile`, kept in
  the `Student` namespace like `Student\EmailNotifications`, now in the settings layout (wide) and
  never locked. The errors modal is the shared `partials/requester-profile-errors`.
- `Student\Requests\Show` shows the request's ID and status; while rejected, an upload form calls
  `SubmitIdentityDocument` then `replaceIdentity` in one transaction.
- `Registrar\Requests\Show` reviews through `RequestWorkflow::reviewIdentity`. The request list
  shows an "ID to verify" badge.
- `/student/profile` is a plain redirect to `/settings/profile`.
