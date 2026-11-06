# 15 — Identity Checked on the Request · Requirements

Source: *Proposed Workflow*, section III-A and the process flowchart ("Create Account and Upload
Valid ID → Verify Account and Identity → Enter Academic Information → Select Credential and
Purpose → Submit Request → Registrar Evaluates Records and Requirements"). The ID is checked as
part of evaluating the request, not in a separate queue.

Decided by the user on 2026-11-06: the registrar's Identity Checks page and the requester's My
Profile page are removed. The requester types their details and uploads their ID on New Request;
they are saved so the next request comes filled in, and Settings → Profile edits them. Every
detail, the ID included, is tied to the request it was sent with, and the ID is verified on that
request. This replaces REQ-01-17..19, REQ-01-21, REQ-01-22, REQ-01-25, REQ-01-27, REQ-01-28 and
REQ-01-30, REQ-01-12's "no settings Profile", REQ-04-01's profile gate, REQ-04-06/07's account
identity, and My Profile in REQ-09-09/12.

## User stories

- **US-15-1** As a requester, I want to give my details and valid ID on the request itself, and
  have them remembered, so that I only type them once.
- **US-15-2** As the registrar, I want to check the requester's ID while I evaluate the request,
  so that verifying identity is part of the same review instead of a separate queue.
- **US-15-3** As the registrar, I want each request to keep the details and ID it was sent with, so
  that a later profile change never rewrites a request already under way.

## Acceptance criteria

### Removed pages
- **REQ-15-01** THE SYSTEM SHALL NOT have an Identity Checks page or nav item for the registrar;
  `/registrar/identities` SHALL respond 404.
- **REQ-15-02** THE SYSTEM SHALL NOT have a My Profile page; the requester's sidebar SHALL show New
  Request and My Requests only, and `/student/profile` SHALL redirect to Settings → Profile (links
  in older notifications keep working).

### New Request
- **REQ-15-03** THE SYSTEM SHALL show New Request as numbered sections: what you need (credential,
  purpose, receiving school), personal information (first, middle (optional) and last name,
  contact number), academic information (the fields of REQ-01-16), valid ID, and who will transact,
  followed by the *Incomplete requirements* notice; the personal and academic sections SHALL start
  filled in from the requester's saved profile.
- **REQ-15-15** WHEN a credential is chosen on New Request THE SYSTEM SHALL show its details card
  (REQ-09-11) and the matching receiving-school field at once, in the browser, without waiting for
  the server.
- **REQ-15-04** WHEN the requester has a saved ID THE SYSTEM SHALL show it (type, upload date, a
  link that opens it in the file viewer) and use it, unless they choose a different one (type of ID
  and an image or PDF file of at most 5 MB). WHEN they have none THE SYSTEM SHALL require one.
  WHEN an ID file is chosen THE SYSTEM SHALL show its name as a link that opens the chosen file in
  the file viewer, in their own browser, before anything is uploaded.
- **REQ-15-05** WHEN the requester chooses "Review request" THE SYSTEM SHALL check every entry and
  save nothing. IF an entry is missing or invalid THEN THE SYSTEM SHALL show the errors modal of
  REQ-01-26. Otherwise THE SYSTEM SHALL show a "Summary" of the request, the personal and academic
  information and the ID (an image as a picture; the file name opens it, image or PDF, in the file
  viewer), the Data Privacy Act (RA 10173) notice, a "Back to edit" button that keeps every entry
  and the chosen file, and a "Submit request" button. THE SYSTEM SHALL show the consent as an
  unticked checkbox on every summary and SHALL NOT submit (the button stays disabled, and the server
  refuses) until it is ticked; the first consent's time is recorded on the account.
- **REQ-15-06** WHEN the requester submits from the summary THE SYSTEM SHALL, all or nothing, save
  the personal and academic information (and a newly chosen ID) to their profile and submit the
  request with a copy of their name, contact number, academic information and the ID used, the ID
  awaiting verification. IF any part fails THEN THE SYSTEM SHALL save nothing, keep no stored file,
  and return to the form with the error.

### Settings → Profile
- **REQ-15-07** THE SYSTEM SHALL give requesters a Settings → Profile page, the first item of their
  settings and where `/settings` takes them, with the personal and academic information and the
  valid ID of New Request, a summary step with the consent checkbox of REQ-15-05 (required on every
  save) and a "Confirm and save" button. It SHALL never be locked, and SHALL say that changes apply to future requests
  while submitted requests keep what they were sent with. Staff SHALL get 403.

### The request's own copy
- **REQ-15-08** THE SYSTEM SHALL keep each request's name, contact number, academic information and
  ID as they were when it was submitted: a later profile change or new ID SHALL NOT change it. The
  requester's, registrar's and offices' request pages and lists, the CSV export and the reports
  SHALL show the request's copy of the name.

### Identity check during evaluation
- **REQ-15-09** THE SYSTEM SHALL show the registrar, on the request page, the request's name,
  contact number and email, a link that opens the request's ID, and the request's identity status
  (awaiting verification, verified or rejected, with the reason). WHILE the ID awaits verification
  and the request is submitted, under evaluation, pending compliance or returned for customer
  action THE SYSTEM SHALL let the registrar verify it or reject it with a reason; each SHALL be
  written to the request's ledger and the requester notified (in the app, and by email if theirs
  are on).
- **REQ-15-10** IF the request's ID is not verified THEN THE SYSTEM SHALL NOT let the registrar mark
  the requirements complete.
- **REQ-15-11** WHILE a request's ID is rejected and the request is still open before acceptance THE
  SYSTEM SHALL show the requester, on the request page, the reason and a form to upload another ID.
  WHEN they upload one THE SYSTEM SHALL store it privately, make it their saved ID, attach it to the
  request awaiting verification, write it to the ledger and notify the registrars.
- **REQ-15-12** THE SYSTEM SHALL mark, on the registrar's request list, the requests whose ID awaits
  verification ("ID to verify").

### Access and existing data
- **REQ-15-13** THE SYSTEM SHALL let only the ID's owner and registrars open an uploaded ID (others
  get 404), and SHALL NOT let a requester act on another requester's request (404).
- **REQ-15-14** WHEN the change is deployed THE SYSTEM SHALL give every existing request its owner's
  current name, contact number and latest ID, with that ID's review status, and SHALL drop the
  per-account identity status.
