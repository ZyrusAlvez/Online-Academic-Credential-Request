# 11 — Representatives & Release · Tasks

- [x] Representatives, documents, releases tables; enums; private document route (REQ-11-01, 02, 04)
- [x] Workflow: declare, review, release with guards (REQ-11-03, 06..08)
- [x] Requester and registrar screens (REQ-11-01..03, 05, 06)
- [x] Pest tests for every REQ-11 criterion
- [x] SPA and representative's ID required on New Request and the request page, enforced by the
      workflow; type and 5 MB checks as soon as a file is chosen, with plain messages (REQ-11-01, 09)
- [x] SPA and representative's ID use the valid ID's picker (`x-file-picker`), on New Request and
      the request page; linked with sizes on the summary (REQ-11-10, REQ-15-17)
- [x] `CredentialRequestPolicy::declareRepresentative`: the owner only while `pending_compliance`;
      the request page shows Add representative only then (REQ-11-01)
- [x] ... and while `ready_for_release`, for someone to claim it (REQ-11-01)
- [x] "Who will transact?" toggles the representative's fields in the browser, no round trip (REQ-11-11)
