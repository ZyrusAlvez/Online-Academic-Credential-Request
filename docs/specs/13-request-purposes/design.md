# 13 — Request Purposes · Design

## Data

`purposes`: `name` (unique), `is_active`. Purposes are deactivated, never deleted. See
`docs/data-dictionary.md`.

`credential_requests.purpose` stays a text column holding the chosen name, as `program` does
(spec 12): a request must keep what was true when it was made, so a rename never rewrites past
requests, and requests made before this list existed keep their free-text purpose.

## Code

- `Registrar\Purposes\Index` (`/registrar/purposes`, "Purposes" under Administration) follows
  `Registrar\Programs\Index`: `PurposeForm`, `PurposePolicy`, a modal form, deactivate instead of
  delete.
- `PurposeSeeder` holds the starter list and only creates missing rows, so a rename or
  deactivation by the registrar survives. A migration runs it, because deploys only migrate (as
  `install_program_list` does).
- `Student\Requests\Create` passes the active purposes to the view; `CredentialRequestForm` checks
  the name with an `exists` rule scoped to active purposes.
- "Others (please specify)" is fixed in code, not a row: it is always last and can't be
  deactivated, so a requester is never stuck when the list lacks their purpose (or is empty). It
  is a sentinel value of the dropdown (`CredentialRequestForm::OTHER_PURPOSE`); choosing it shows a
  required `purpose_other` text box, and the typed text is stored as `credential_requests.purpose`
  (the column's 500-character limit). A purpose that comes up often is one the registrar should add
  to the list.

## Starter list

Board or licensure examination · Employment · Employment abroad · Further studies · Personal copy ·
Scholarship application · Transfer to another school · Visa or immigration. The demo data uses these
names.
