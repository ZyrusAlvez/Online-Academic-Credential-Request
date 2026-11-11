# 21 — Details Verified Once · Design

## Data

`requester_verifications` (model `RequesterVerification`, written only by `RequestWorkflow`):

| column | |
|---|---|
| `user_id` | the requester, cascade on delete |
| `part` | `CheckItem::Identity` or `CheckItem::AcademicRecord` |
| `credential_request_id` | the request whose copy was verified |
| `verified_by`, `verified_at` | the registrar who completed that evaluation, and when |

Unique on (`user_id`, `part`). Dropping a verification deletes the row.

`request_checks.carried_from_request_id` (nullable) marks a part confirmed from an earlier
verification (REQ-21-04, 05). `recordCheck` and `confirmCompliance` set it back to null.

The migration fills `requester_verifications` from each requester's latest request with
`requirements_completed_at` and an `identity_reviewed_by` (REQ-21-07).

## Comparing details: `App\Support\VerifiedDetails`

One place lists what each part covers, and compares a profile (`User`) or a request's copy
(`CredentialRequest`) with the verified request's copy:

- `identity`: `name` (`User::requesterName()` / `requester_name`), `contact_number`, `id`
  (`latestIdentityDocument` / `identity_document_id`).
- `academic`: `academic_status`, `college_id`, `program`, `student_number`, `attended_from`,
  `attended_to`.

Sections for the requester's badges (REQ-21-06): `personal` = name and contact number, `valid_id`
= id (both against the identity verification), `academic` = every academic field.

Comparing instead of hooking every edit means Settings → Profile, New Request, Edit Request and
Remove saved ID need no changes: a different value simply no longer matches (REQ-21-02).

## Workflow

- `submit` and `resubmit` call `carryVerifiedParts()` once the request's copy is set: for each part
  with no check row whose copy equals the verified copy, insert a confirmed check by the verifier
  at the verification time, with `carried_from_request_id`; for identity, set `identity_status`
  verified with the same reviewer and time.
- `completeRequirements` (Approve and Confirm Compliance) calls `recordVerifications()`: for each
  part, skip it if the requester is already verified with these details, otherwise record this
  request when its copy equals their saved details.
- `returnForClarification` deletes the requester's verification of each rejected part.

A request submitted while an earlier one is still being evaluated is not confirmed afterwards
when that earlier one completes; its evaluator checks it as before.

## Screens

- Registrar request page, evaluation checklist: `<x-check-card>` takes the part's `RequestCheck`
  when carried, and shows the note with the verifier, the date and a link to the earlier
  request's record (`records.show`).
- `partials/requester-profile-fields`: a `<x-verification-badge>` beside each heading and the note
  under a verified section; the component passes `$verified` (section → bool) from
  `VerifiedDetails::sections($user)`.
