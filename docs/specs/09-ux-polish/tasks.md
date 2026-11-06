# 09 — UX Polish · Tasks

- [x] `viewable` on every password field; minimum-length hint on new passwords; fix
      confirm-password autocomplete (REQ-09-01..03)
- [x] Forward `$title` through the app and auth layouts; `#[Title]` on Volt pages (REQ-09-04)
- [x] `<x-copy-button>` on the three request pages (REQ-09-05)
- [x] `wire:confirm` on deactivate actions (REQ-09-06)
- [x] `<x-confirm-dialog>` + `directive.init` hook replace the browser dialog; titles, labels and
      danger variants on every `wire:confirm` (REQ-09-13)
- [x] Verify-email page: auth header, brand colors, address shown (REQ-09-07)
- [x] Requester sidebar: no "Student" heading, New Request first (REQ-09-09)
- [x] New Request: incomplete-requirements notice collapsed, above Submit (REQ-09-10)
- [x] New Request: credential details card with a numbered office route (REQ-09-11)
- [x] Same column and section style on New Request, My Requests and My Profile (REQ-09-12)
- [x] Pest tests for every REQ-09 criterion
- [x] `composer qa` green (CI, deploy run on staging)
- [x] `<x-password-checklist>` under every new-password field, ticking rules as the user types (REQ-09-02)
- [x] Pest tests for the changed REQ-09-02
- [ ] `<x-file-link>` and `<x-file-viewer>` for every uploaded file (REQ-09-14)
- [ ] Download through `?download=1` on the three file routes (REQ-09-15)
- [ ] Pest tests for REQ-09-14, 15
