# 09 — UX Polish · Requirements

## User stories

- **US-09-1** As anyone typing a password, I want to see what I typed, so that I don't get locked
  out by a typo I couldn't see.
- **US-09-2** As a user with several tabs open, I want each tab to say which page it is.
- **US-09-3** As a student, I want to copy my reference number in one click, so that I can quote it
  to the registrar or paste it in the payment portal.
- **US-09-4** As the registrar, I want a second chance before I deactivate something, so that one
  mis-click doesn't lock out a staff member or pull a credential from students.
- **US-09-5** As the registrar, I want confirmations to look like the rest of the app, so that they
  don't show the site's address like a browser warning and name the action I'm about to take.

## Acceptance criteria

### Password fields
- **REQ-09-01** THE SYSTEM SHALL give every password field (log in, register, reset password,
  confirm password, change password, staff account form) a button that shows or hides the typed
  password.
- **REQ-09-02** WHERE a user chooses a new password (register, reset password, change password,
  staff account form) THE SYSTEM SHALL list the password rules under the field as a checklist —
  at least 8 characters, an uppercase letter (A-Z), a lowercase letter (a-z), a number (0-9) and a
  special character (REQ-01-03) — and, WHILE the user types, mark each rule the password already
  meets with a green check, before the form is submitted.
- **REQ-09-03** THE SYSTEM SHALL mark fields for an existing password `autocomplete="current-password"`
  and fields for a new password `autocomplete="new-password"`, so that password managers fill
  the right one.

### Page titles
- **REQ-09-04** THE SYSTEM SHALL give every page a browser tab title of the form
  "<page> · <app name>", including the log in, register, password and settings pages.

### Reference numbers
- **REQ-09-05** THE SYSTEM SHALL show a "copy tracking number" button next to the tracking
  number on the requester, office and registrar request pages; WHEN it is pressed THE SYSTEM
  SHALL copy the number to the clipboard and confirm with a toast.

### Confirmations
- **REQ-09-06** WHEN the registrar deactivates an account, a department or a credential type THE
  SYSTEM SHALL ask for confirmation first. Activating needs no confirmation.
- **REQ-09-13** WHEN THE SYSTEM asks for confirmation (deactivating, removing, verifying an
  identity or a representative) it SHALL do so in its own dialog, in the app's style, with a title,
  the message, a Cancel button and a confirm button named after the action, and never with the
  browser's built-in confirm or alert box.

### Viewing uploaded files
- **REQ-09-14** WHEN a user opens an uploaded file (a valid ID, a proof of payment, an SPA or a
  representative's ID) THE SYSTEM SHALL show it in a viewer on the same page instead of a new
  browser tab:
  - an image fitted to the screen, or a PDF shown inline; any other file offers the download;
  - a loading indicator until the image or PDF has loaded, and a message if it can't be loaded;
  - the file's name, a Download button, an "Open in new tab" link and a Close button.

  This includes a file chosen on New Request but not sent yet: a PDF SHALL be shown inline even
  though Livewire's signed preview serves it as a download (the viewer shows the browser's copy of
  it), and its Download SHALL use that preview address unchanged, since an added `?download=1`
  breaks the signature. *(User's report, 2026-11-11: a chosen PDF stayed on "Loading…".)*

  Escape and Close SHALL close the viewer. A Ctrl-, ⌘- or middle-click SHALL still open the file
  in a new tab, and without JavaScript the link SHALL open the file as before.
- **REQ-09-16** WHILE an uploaded image shown on a page (the proof of payment on the registrar's
  request page) is still loading THE SYSTEM SHALL show a loading placeholder in its place.
- **REQ-09-15** THE SYSTEM SHALL serve an uploaded file as a download when asked, under the same
  access rules as viewing it (REQ-01-20) and without letting browsers cache it.

### Notifications on screen
- **REQ-09-08** THE SYSTEM SHALL show toast notifications at the top centre of the screen on every
  screen size.

### Navigation
- **REQ-09-09** THE SYSTEM SHALL show the requester's sidebar links without a group heading, in the
  order New Request, Request Tracker (My Profile was removed by REQ-15-02; "Request Tracker" was
  "My Requests" until 2026-11-11, REQ-09-21).

### New Request
- **REQ-09-10** THE SYSTEM SHALL show New Request's *Incomplete requirements* notice (REQ-04-02)
  between "Who will transact?" and the "Review request" button, collapsed to its heading until the
  requester opens it.
- **REQ-09-11** WHEN a credential is chosen on New Request THE SYSTEM SHALL show its details
  (REQ-04-02) as a card: the fee and the processing period side by side, then the offices of its
  route as a numbered list, one office per line with its task (the Dean step names the requester's
  college), ending with the Office of the Registrar's final quality check, then the special
  conditions as a note. *(Changed by REQ-15-03, 2026-11-10: the documentary requirements are
  section 2 of New Request, no longer in the card.)*
- **REQ-09-12** THE SYSTEM SHALL lay out New Request and Request Tracker alike: one centred
  column of the same width, with the content in bordered sections that share their padding and
  heading style; Request Tracker lists the requests inside one section, with no heading of its own
  above the list *(user's request, 2026-11-11: the "Your Requests" heading is removed)*.
- **REQ-09-20** THE SYSTEM SHALL show a search box at the top of Request Tracker that, as the requester
  types, narrows their list to the requests whose credential name or tracking number contains the
  text (any case), back to the first page; the search stays in the address so a reload keeps it.
  WHEN nothing matches THE SYSTEM SHALL say so and offer to clear the search. It only ever searches
  the requester's own requests. *(User's request, 2026-11-11.)*
- **REQ-09-21** THE requester's list of requests SHALL be named "Request Tracker" (sidebar, page
  title, browser tab, breadcrumb), and the requester's request pages SHALL live under
  `/student/request/` (singular): `/student/request/new`, `/student/request/<tracking no.>` and
  `/student/request/<tracking no.>/edit`. WHEN a requester opens an old `/student/requests/…`
  address (links in earlier emails) THE SYSTEM SHALL redirect them to the new one. New Request
  SHALL have no breadcrumb above its title. *(User's request, 2026-11-11.)*
- **REQ-09-22** THE requester's request page SHALL show "Tracking History" as its last section,
  after "Request Details". "Request Details" SHALL list each entry as a row, the field's name on
  the left and its value on the right (stacked on narrow screens, like the summary rows of
  REQ-15-17), the valid ID and each documentary requirement with its uploaded files included as
  rows of the same list; a requirement without a file reads "No file uploaded" in grey italics
  (REQ-15-19). The same rows SHALL be used wherever Request Details appears (office and Records
  pages) and for the documentary requirements on the Initial Evaluation checklist.
  *(User's request, 2026-11-11.)*

### Email verification
- **REQ-09-07** THE SYSTEM SHALL show the verify-email page with the same heading style as the
  other sign-in pages and name the address the code was sent to.

### Text input
- **REQ-09-17** THE SYSTEM SHALL trim leading and trailing spaces from text input (except
  passwords) before validating or storing it, but SHALL leave a search box exactly as typed, so a
  space between words survives a pause in typing; the search itself ignores the outer spaces.

### Page width
- **REQ-09-18** THE SYSTEM SHALL fit every signed-in page to the screen, from a 320px phone to a
  wide monitor. On a wide screen the page content stops at 96rem (about a 1920px monitor) and is centred beside the sidebar
  (requester pages keep their narrower column, REQ-09-12). On a small screen nothing makes the
  page scroll sideways: a table scrolls inside its own box with its less-needed columns hidden, and
  long emails and payment references wrap.

### Section headings
- **REQ-09-19** THE SYSTEM SHALL write every section heading on a page in Title Case (e.g.
  "Request Details", "Office Visits", "Proofs of Payment"; short articles, conjunctions and
  prepositions stay lower case unless first), including the report names. Dialog titles, field
  labels and buttons keep sentence case. The request's event ledger is headed "Tracking History"
  on every request page (requester, office and Records), not "Ledger" or "History".
