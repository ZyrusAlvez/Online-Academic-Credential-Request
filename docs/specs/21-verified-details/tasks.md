# 21 — Details Verified Once · Tasks

- [x] Migration: `requester_verifications`, `request_checks.carried_from_request_id`, backfill
      (REQ-21-01, 07)
- [x] `RequesterVerification` model, `User::verifications()`, `VerifiedDetails` (REQ-21-02)
- [x] Workflow: record on completion, carry on submit and resubmit, drop on a rejected return,
      clear the carried mark when a part is checked (REQ-21-01, 03, 04, 05)
- [x] Registrar checklist: "Already verified by …" note with a link to the earlier request's
      record (REQ-21-05)
- [x] Requester: badges on Personal Information, Academic Information and Valid ID
      (REQ-21-06)
- [x] Badge at the right end of the heading; re-verify note and "remove this one first" line
      removed (user's request, 2026-11-11)
- [x] `RemoveCollegeOfLawSeeder` deletes the verifications of the requests it removes
- [x] Pest tests for every REQ-21 criterion
- [x] Spec notes in 15 and 16
