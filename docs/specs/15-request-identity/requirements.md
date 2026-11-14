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
  Request and Request Tracker (formerly My Requests, REQ-09-21), then Help (REQ-22-01, since
  2026-11-11), and no profile link; `/student/profile` SHALL redirect to Settings → Profile (links
  in older notifications keep working).

### New Request
- **REQ-15-03** THE SYSTEM SHALL show New Request as numbered sections: what you need (credential,
  purpose, receiving school), documentary requirements (the chosen credential's upload fields,
  REQ-04-18), personal information (first, middle (optional) and last name, contact number),
  academic information (the fields of REQ-01-16), valid ID, and who will transact, followed by the
  *Incomplete requirements* notice; the personal and academic sections SHALL start filled in from
  the requester's saved profile. *(User's decision, 2026-11-10: documentary requirements are
  section 2, no longer inside the credential's card.)*
- **REQ-15-15** WHEN a credential is chosen on New Request THE SYSTEM SHALL show its details card
  (REQ-09-11), its documentary requirements and the matching receiving-school field at once, in the
  browser, without waiting for the server.
- **REQ-15-16** THE credential dropdown on New Request SHALL open just below the field as a list of
  limited height that scrolls, with a search box that filters it by name, so that every credential
  can be seen and chosen on any screen; it SHALL work with the keyboard (arrows, Enter, Escape) and
  with screen readers. *(User's report, 2026-11-10: the browser's own list ran off the screen.)*
- **REQ-15-04** WHEN the requester has a saved ID THE SYSTEM SHALL show it (type, upload date, a
  link that opens it in the file viewer) and use it. A requester has one saved ID at most: while
  one is saved THE SYSTEM SHALL NOT show the type of ID and file fields, and another ID can be
  given only after removing it (REQ-15-18). WHEN they have none THE SYSTEM SHALL require one (type
  of ID and an image or PDF file of at most 5 MB). *(User's request, 2026-11-10: replaces "unless
  they choose a different one".)* A request returned because its ID was not accepted still asks for
  another one (REQ-20-06). WHEN an ID file is chosen THE SYSTEM SHALL show its name as a link that opens the chosen file in
  the file viewer, in their own browser, before anything is uploaded. The name SHALL appear once,
  next to a "Choose file" (then "Change file") button; the browser's own picker, which repeats it,
  is hidden, and the upload shows the progress bar of REQ-04-18. *(User's report, 2026-11-10.)*
- **REQ-15-18** WHEN the requester has a saved ID THE SYSTEM SHALL show a "Remove" button beside it,
  on New Request, Edit Request and Settings → Profile, and remove it from their profile once they
  confirm, at once and without the summary step; the type of ID and file fields then appear and an
  ID is required again. Requests already sent with that ID SHALL keep it (REQ-15-08), so the
  registrar can still open it; an ID no request uses SHALL be deleted with its file. The removal is
  recorded in the activity log. *(User's request, 2026-11-10.)*
- **REQ-15-05** WHEN the requester chooses "Review request" THE SYSTEM SHALL check every entry and
  save nothing. IF an entry is missing or invalid THEN THE SYSTEM SHALL show the errors modal of
  REQ-01-26. Otherwise THE SYSTEM SHALL show a "Summary" of the request, the personal and academic
  information and the ID (one row like a requirement's in section 2: the type of ID on the left,
  and on the right its file name, as a link that opens it, image or PDF, in the file viewer,
  followed by its size; no picture on the page, *user's requests, 2026-11-10 and 2026-11-11*), the Data Privacy Act (RA 10173) notice, a "Back to edit" button that keeps every entry
  and the chosen file, and a "Submit request" button. THE SYSTEM SHALL show the consent as an
  unticked checkbox on every summary and SHALL NOT submit (the button stays disabled, and the server
  refuses) until it is ticked; the first consent's time is recorded on the account.
- **REQ-15-17** THE request summary SHALL repeat the form's numbered sections (1. What Do You
  Need? to 6. Who Will Transact?, or 1 to 5 on Edit Request), each entry as a row with its label
  on the left and its value on the right (stacked on narrow screens), section 2 listing every
  requirement with the names and sizes of the files chosen for it, each name a link that opens the
  file in the file viewer (*user's request, 2026-11-11*), and section 6 the representative's name,
  relationship, contact number, and the SPA and their ID as linked file names with sizes like
  section 2's (*user's request, 2026-11-11*). "Summary" SHALL be the page's title, in place of
  the form's title and description. *(User's request, 2026-11-10.)*
- **REQ-15-19** WHERE a summary row has nothing to show ("Not provided", "No files", "No new
  files", "None for this credential") THE SYSTEM SHALL write that text in italics and grey
  (`text-zinc-600`), unlike an entered value, so that it is never mistaken for one (a receiving
  school typed as "Not provided" stays plain). This applies to the summaries of New Request, Edit
  Request and Settings → Profile. *(User's request, 2026-11-11.)*
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
  requester's, registrar's and offices' request pages and lists and the reports SHALL show the
  request's copy of the name. *Since spec 21 (2026-11-11):* details verified once stay verified
  for later requests that carry the same copy (REQ-21-01..06).

### Identity check during evaluation
- ~~REQ-15-09, REQ-15-10~~ *Superseded by REQ-16-10, 12 and 13 (2026-11-06):* the ID is the User
  identity part of the evaluation checklist; approving verifies it, and returning with that part
  rejected rejects it.
- ~~REQ-15-11~~ *Superseded by REQ-20-06 and 07 (2026-11-10):* a rejected ID comes back with the
  request returned for clarification, and the requester uploads another on Edit Request. It
  becomes their saved ID and awaits verification when they resubmit.
- ~~REQ-15-12~~ *Superseded by REQ-16-02:* there is no request list; every request in the Initial
  Evaluation queue has its ID checked.

### Access and existing data
- **REQ-15-13** THE SYSTEM SHALL let only the ID's owner and registrars open an uploaded ID (others
  get 404), and SHALL NOT let a requester act on another requester's request (404).
- ~~REQ-15-14~~ *Superseded by REQ-23-01 (2026-11-15):* the one-time copy of each owner's details
  onto existing requests is gone; the tables are created in their final shape and both servers are
  reset.
