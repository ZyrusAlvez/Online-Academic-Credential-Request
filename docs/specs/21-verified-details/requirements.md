# 21 — Details Verified Once · Requirements

Source: *Proposed Workflow*, section III-A and the process flowchart ("Create Account and Upload
Valid ID → Verify Account and Identity → Enter Academic Information → …"): the account and identity
are verified once, before the requests that follow, not again on every request.

Decided by the user on 2026-11-11. Spec 15 checks the requester's identity on every request
(REQ-15-08, REQ-16-10). Now the Office of the Registrar verifies a requester's identity (name,
contact number, valid ID) and academic information once; later requests that carry the same details
have those checklist parts confirmed already, showing who verified them. The requester sees whether
their details are verified, and may still edit them, which sends them back for verification. The
evaluator can still reject an already-verified part. Each request still keeps its own copy of the
details (REQ-15-08).

## User stories

- **US-21-1** As a requester, I want my personal information, academic information and valid ID
  verified once, so that I don't wait for the same check on every request.
- **US-21-2** As a requester, I want to see which of my details are verified, and to be able to
  correct them, knowing a change is verified again.
- **US-21-3** As an evaluator, I want parts verified earlier to be confirmed already, with who
  verified them and when, so that I only check what is new, and can still reject them.

## Acceptance criteria

### What counts as verified
- **REQ-21-01** WHEN a request's evaluation is completed (Approve, REQ-16-12, or Confirm
  Compliance, REQ-20-09) THE SYSTEM SHALL record, for each of the parts User Identity Verification
  and Academic Record Verification, that the requester's details were verified on that request, by
  that registrar, at that time, provided the request's copy of them still equals the requester's
  saved details. A part already verified with the same details SHALL keep its first verification.
- **REQ-21-02** A part SHALL count as verified WHILE the requester's saved details equal the copy on
  the request it was verified on. The identity details are the name, the contact number and the
  saved valid ID; the academic details are the status, college, program, student number and years
  attended. Any change, from Settings → Profile, New Request or Edit Request, or removing the saved
  ID (REQ-15-18), SHALL make the part unverified again.
- **REQ-21-03** WHEN a request is returned for clarification with the User Identity Verification or
  Academic Record Verification part rejected (REQ-20-03) THE SYSTEM SHALL drop the requester's
  verification of that part.

### On the request
- **REQ-21-04** WHEN a requester submits or resubmits a request whose copy of a part equals the
  copy that part was verified on THE SYSTEM SHALL confirm that checklist part at once, as confirmed
  by the registrar who verified it, at that time; for User Identity Verification the request's ID
  SHALL be marked verified too. A part with a result already, or whose details differ, SHALL be
  left to the evaluator.
- **REQ-21-05** ON the evaluation checklist, a part confirmed this way SHALL say "Already verified
  by <name> on <date>" and the tracking number of the request it was verified on, as a link to that
  request. The evaluator SHALL still be able to reject it or confirm it again; either replaces the
  automatic result and its note.

### What the requester sees
- **REQ-21-06** ON New Request, Edit Request and Settings → Profile, THE SYSTEM SHALL show at the
  right end of the headings Personal Information, Academic Information and Valid ID a badge for
  the saved details: "Verified" (green) while they are verified, and no badge otherwise. Personal
  Information is verified when the name and contact number equal the verified identity copy, Valid
  ID when the saved ID is the verified one, Academic Information when the academic part is
  verified. The fields SHALL stay editable. The sections carry no note explaining re-verification;
  Help explains it (REQ-22-03). *(User's request, 2026-11-11: badge on the right, explanatory notes
  removed. User's request, 2026-11-13: no "For verification" badge, only "Verified".)*

### Existing data
- **REQ-21-07** WHEN the change is deployed THE SYSTEM SHALL record, for each requester, their
  latest request whose evaluation was completed as the request both parts were verified on, by the
  registrar who reviewed its ID, at that time; it counts only while their details still equal it
  (REQ-21-02).
