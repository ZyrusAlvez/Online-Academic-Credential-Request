# 11 — Representatives & Release · Requirements

Source: *Proposed Workflow*, section IV (special authorization rules) and VIII-B (release and
claiming procedure).

## User stories

- **US-11-1** As a requester who cannot transact in person, I want to declare an authorized
  representative with their SPA, so that they can submit documents or claim the credential for me.
- **US-11-2** As the registrar, I want to record whether a representative's SPA and IDs are
  verified, so that no one acts for an owner without authority.
- **US-11-3** As the registrar, I want to record who received each released credential, so that
  every release is accountable.

## Acceptance criteria

### Representatives
- **REQ-11-01** THE SYSTEM SHALL let the owner declare that they act through an authorized
  representative when submitting (New Request, "Who will transact?"), and later only WHILE the
  evaluator requires an office visit (`pending_compliance`, REQ-20-04), when the Proposed Workflow
  lets the owner "visit UPHSL personally or send an authorized representative" (III-C, step 3).
  At any other time the request page SHALL show no "Add representative" button, and the server
  SHALL refuse it (403); a representative already declared is still listed. The owner gives the
  representative's full name, relationship and contact number (exactly 11 digits when given), and
  uploads the Special Power of Attorney (SPA) and the representative's valid ID (JPG, PNG, WebP or
  PDF, max 5 MB each, stored privately). Both uploads are required, on New Request and on the
  request page alike; a representative without them is not saved. *(User's decision, 2026-11-11:
  replaces "or later while the request is open".)*
- **REQ-11-02** THE SYSTEM SHALL record the representative's identity separately from the owner's
  account and show the owner and the registrar the representative and their verification status.
- **REQ-11-03** THE SYSTEM SHALL let the registrar verify a representative (SPA and IDs checked,
  online or presented in person) or reject them: during the evaluation through the Representative
  part of the checklist (REQ-16-12, 13), and on the release page for one declared later, with a
  reason (REQ-16-20).
- **REQ-11-04** THE SYSTEM SHALL let only the owner and registrars open a representative's
  documents (404 for others), without letting browsers cache them.
- **REQ-11-09** WHEN the owner chooses an SPA or representative's ID that is not a JPG, PNG, WebP
  or PDF file, or is larger than 5 MB, THE SYSTEM SHALL say so under that field as soon as it is
  chosen, naming the accepted types or the 5 MB limit, and SHALL NOT save the representative
  until both files are acceptable. The form states the accepted types and the limit beforehand.
- **REQ-11-10** THE SPA and representative's ID fields, on New Request and on the request page,
  SHALL be the same picker as the valid ID (REQ-15-04): a Choose file / Change file button, the
  chosen file's name once beside it as a link that opens the file in the file viewer, and a
  progress bar while it uploads. *(User's request, 2026-11-11.)*

### Release
- **REQ-11-05** WHILE a request is `ready_for_release` THE SYSTEM SHALL show the requester that the
  credential can be claimed and what to bring.
- ~~REQ-11-06~~ *Superseded by REQ-16-20:* release records who claimed the credential (the owner or
  a verified representative), the time and the staff member, without the ID presented or the
  checkboxes.
- **REQ-11-07** IF the claimant is a representative who is not verified THEN THE SYSTEM SHALL NOT
  mark the credential released.
- **REQ-11-08** WHEN a credential is released THE SYSTEM SHALL store its processing time in working
  days from acceptance to release.
