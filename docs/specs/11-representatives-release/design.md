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
| `declareRepresentative(owner, data, spa, id)` — both files required (REQ-11-01) | representative_declared |
| `reviewRepresentative(rep, registrar, verified, remarks)` | representative_verified / representative_rejected |
| `release(request, registrar, claim)` — ready_for_release → released; guards REQ-11-07; freezes `processing_working_days` | released |

## Upload checks (REQ-11-01, 09)

`App\Livewire\Concerns\ValidatesRepresentativeDocuments` holds the `spa` and `representativeId`
properties and their rules (`required`, `file`, `mimes:jpg,jpeg,png,webp,pdf`, `max:5120`) with
plain messages ("The SPA must be a JPG, PNG, WebP or PDF file.", "… must be 5 MB or smaller.").
New Request and the request page both use it; `updatedSpa()`/`updatedRepresentativeId()` check a
file as soon as it is chosen, and submit checks both again.

A file too big for Livewire's temporary upload (12 MB) or for PHP on the server (8 MB) fails
before those rules run; `validation.custom.files.*` and `validation.attributes` in
`lang/en/validation.php` word that failure plainly ("The file could not be uploaded. Use a JPG,
PNG, WebP or PDF file of 5 MB or smaller.") instead of "The spa field must not be greater than
12288 kilobytes".

## Screens

- Requester: representative section on the request form and request page (declare, upload,
  status); release instructions when ready. The SPA, the representative's ID and the valid ID all
  use `x-file-picker` (REQ-11-10): the picker's Alpine state previews the browser's own copy in
  `x-file-viewer`, follows Livewire's upload events for the progress bar, and clears when the
  component resets the field.
- New Request's "Who will transact?" radio is a deferred `wire:model`; the representative's
  fields are always on the page and `x-show` on `$wire.form.requester_mode` toggles them, so
  choosing needs no server round trip (REQ-11-11), like the credential details (REQ-15-15).
- Registrar request page: representatives with documents and verify/reject; the release form
  (claimant, name, ID presented, verified checks, acknowledgment).
