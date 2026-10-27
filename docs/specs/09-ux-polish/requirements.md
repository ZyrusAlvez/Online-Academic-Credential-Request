# 09 — UX Polish · Requirements

## User stories

- **US-09-1** As anyone typing a password, I want to see what I typed, so that I don't get locked
  out by a typo I couldn't see.
- **US-09-2** As a user with several tabs open, I want each tab to say which page it is.
- **US-09-3** As a student, I want to copy my reference number in one click, so that I can quote it
  to the registrar or paste it in the payment portal.
- **US-09-4** As the registrar, I want a second chance before I deactivate something, so that one
  mis-click doesn't lock out a staff member or pull a credential from students.

## Acceptance criteria

### Password fields
- **REQ-09-01** THE SYSTEM SHALL give every password field (log in, register, reset password,
  confirm password, change password, staff account form) a button that shows or hides the typed
  password.
- **REQ-09-02** WHERE a user chooses a new password THE SYSTEM SHALL state the minimum length
  (8 characters) next to the field before the form is submitted.
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

### Notifications on screen
- **REQ-09-08** THE SYSTEM SHALL show toast notifications at the top centre of the screen on every
  screen size.

### Email verification
- **REQ-09-07** THE SYSTEM SHALL show the verify-email page with the same heading style as the
  other sign-in pages and name the address the code was sent to.
