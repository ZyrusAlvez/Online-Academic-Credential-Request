# 11 — Representatives & Release · Design

## Data

`request_representatives`: request, `full_name`, `relationship`, `contact_number`, `status`
(`ReviewStatus`), `declared_by`, `reviewed_by`, `reviewed_at`, `remarks`.

`request_documents`: request, representative (nullable), `kind` (`DocumentKind`: `spa`,
`representative_id`), `path`, `original_name`, `mime_type`, `size`, `uploaded_by`. Files go to
`request-documents/{request}/` on the private receipts disk; `RequestDocumentController` streams
them (`no-store`) for the owner and registrars only.

`releases` (one per request): `recipient_type` (`owner`/`representative`),
`request_representative_id`, `recipient_name`, `id_presented`, `acknowledged`, `released_by`,
`released_at`, `remarks`.

`credential_requests.requester_mode` is `representative` once one is declared.

## Workflow

| Method | Ledger |
|---|---|
| `declareRepresentative(owner, data, spa?, id?)` | representative_declared |
| `reviewRepresentative(rep, registrar, verified, remarks)` | representative_verified / representative_rejected |
| `release(request, registrar, claim)` — ready_for_release → released; guards REQ-11-07; freezes `processing_working_days` | released |

## Screens

- Requester: representative section on the request form and request page (declare, upload,
  status); release instructions when ready.
- Registrar request page: representatives with documents and verify/reject; the release form
  (claimant, name, ID presented, verified checks, acknowledgment).
