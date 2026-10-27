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
- **REQ-11-01** THE SYSTEM SHALL let the owner declare, when submitting or later while the request
  is open, that they act through an authorized representative, giving the representative's full
  name, relationship and contact number (exactly 11 digits when given), and optionally uploading the Special Power of Attorney
  (SPA) and the representative's valid ID (images or PDF, max 5 MB each, stored privately).
- **REQ-11-02** THE SYSTEM SHALL record the representative's identity separately from the owner's
  account and show the owner and the registrar the representative and their verification status.
- **REQ-11-03** THE SYSTEM SHALL let the registrar verify a representative (SPA and IDs checked,
  online or presented in person) or reject them with a reason.
- **REQ-11-04** THE SYSTEM SHALL let only the owner and registrars open a representative's
  documents (404 for others), without letting browsers cache them.

### Release
- **REQ-11-05** WHILE a request is `ready_for_release` THE SYSTEM SHALL show the requester that the
  credential can be claimed and what to bring.
- **REQ-11-06** THE SYSTEM SHALL let the registrar release a credential by recording the claimant
  (the owner, or a representative of the request), the recipient's name, the ID presented and the
  recipient's acknowledgment of receipt; the release time and the releasing staff member are
  recorded automatically (status `released`).
- **REQ-11-07** IF the claimant is a representative who is not verified, or the owner's identity
  or the recipient's acknowledgment is not confirmed, THEN THE SYSTEM SHALL NOT mark the credential
  released.
- **REQ-11-08** WHEN a credential is released THE SYSTEM SHALL store its processing time in working
  days from acceptance to release.
