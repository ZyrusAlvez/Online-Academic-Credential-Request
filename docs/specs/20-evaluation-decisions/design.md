# 20 — Initial Evaluation Decisions · Design

## Status

`RequestStatus::ReturnedForClarification = 'returned_for_clarification'`, the third case, after
`UnderEvaluation`. It is before acceptance (`beforeAcceptance()`), so the requester can cancel it,
and its colour is amber like `pending_compliance`.

Migration `2026_11_10_000003_add_returned_for_clarification_status` inserts the row into
`request_statuses` and renumbers `sort_order` to the enum's order (REQ-19-02). It also rewrites
the meaning of `pending_compliance`. No request or ledger row changes.

## Ledger

| Decision | Event type | Remarks |
|---|---|---|
| Return for Clarification | `customer_action_requested` (existing) | "Rejected: <parts>\n<remarks>" |
| Require Office Visit | `compliance_requested` (existing) | "Required: <items>\n<remarks>" (older entries: "Missing: …") |
| Mark Received | `deficiency_resolved` (existing) | the item |
| Confirm Compliance | `requirements_completed` (existing) | — |
| Resubmit Request | `resubmitted` (existing) | the note |

`evaluation_reopened` stays for older ledgers; nothing records it any more.

## RequestWorkflow

- `returnForClarification($request, $registrar, $remarks)` replaces `returnToRequester()`: the same
  checks and side effects, to `ReturnedForClarification`.
- `requireOfficeVisit($request, $registrar, list<string> $missing, $remarks)`: under evaluation, by
  its evaluator; creates a `request_deficiencies` row per item (`recorded_by` the evaluator), then
  `PendingCompliance`.
- `markReceived(RequestDeficiency $deficiency, $registrar)`: pending compliance only; sets
  `resolved_at`, `resolved_by`.
- `confirmCompliance($request, $registrar, ?$remarks)`: pending compliance, every deficiency
  resolved; writes Confirmed for every evaluation part, then the same path as
  `approveEvaluation()` (ID and representatives verified, `requirements_completed_at`, awaiting
  payment or accepted). The shared part becomes a private `completeRequirements()`.
- `resubmit($request, $owner, ?$note, ?array $changes, ?IdentityDocument $identity)`: from
  `ReturnedForClarification` only. With `$changes` (credential, purpose, receiving school) it
  updates them, recomputes the fee and copies the owner's details again. A new `$identity` is
  attached as pending. Then it clears the rejected checks and the checks of changed parts, and
  moves to `Submitted`.
- `decline()` also accepts `PendingCompliance`.
- `reopenEvaluation()` and `replaceIdentity()` are removed. `attachRequirementDocuments()` is
  called after `resubmit()` in the same transaction, while the request is `Submitted`.

## Screens

**Registrar, `Registrar\Requests\Show`**
- Under evaluation: the decision row is Decline, Require Office Visit, Return for Clarification,
  Approve. Require Office Visit opens the `office-visit` modal: the credential's requirements as
  checkboxes under "Requirements", an Other field and the Remarks. The request record's list of
  them is headed "Office Visit Requirements" (REQ-20-04). A decision that can't run uses the existing
  "Can't … yet" dialog (REQ-17-17).
- Returned for clarification (`PANEL_CLARIFICATION`): the remarks and "It comes back to Initial
  Evaluation when the requester resubmits."
- Pending compliance (`PANEL_COMPLIANCE`): the missing items, each with Mark Received or the date
  and registrar who received it; Remarks; Decline and Confirm Compliance.

**Requester, `Student\Requests\Show`**
- Returned for clarification: the rejected parts, the remarks and **Edit Request** (a link).
- Pending compliance: "Visit the Office of the Registrar", the required items with their received
  state, the remarks and the in-person notice (naming the declared representative, if any,
  REQ-11-05); no form.
- The separate "Your Valid ID Was Not Accepted" section is removed (REQ-15-11 superseded).

**Requester, `Student\Requests\Edit`** at `/requests/{reference}/edit` (`student.requests.edit`)
- Extends `Create`: the same form, view and summary, with `$editing` set. Mount authorizes the
  `edit` policy (owner, `returned_for_clarification`) and fills the form from the request.
- Section 5 (representative) is not shown; a representative is declared on New Request only
  (REQ-11-01).
- When the request's ID is rejected, the Valid ID section shows no saved ID and the ID is
  required. The reason is not repeated here: the request page shows it (REQ-20-05, 06).
- The summary ends with a note field, the consent and Resubmit Request. Submit runs profile save,
  ID storage, `resubmit()` and `attachRequirementDocuments()` in one transaction, and removes any
  stored file if it fails.

## Policy

`CredentialRequestPolicy::edit` (owner and `returned_for_clarification`) replaces `resubmit` and
`replaceIdentity`. `cancel` allows both waiting statuses through `beforeAcceptance()`.

## Queues, wording, reports

- `StageQueue` waiting view and `RegistrarRequestFilter::waitingForRequester()`: both statuses.
- `whereabouts()` / `staffWhereabouts()`: REQ-20-11.
- `ComplianceReport`: `ReturnedForClarification` waits for the `customer_action_requested`
  remarks. `EvaluatorReport`: a "Returned for clarification" column counting requests with a
  `customer_action_requested` event; "Sent for compliance" keeps counting requests with
  deficiencies.
- `DemoSeeder`: one request returned for clarification and one pending compliance with a missing
  item received.
