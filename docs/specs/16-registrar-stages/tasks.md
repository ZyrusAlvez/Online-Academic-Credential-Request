# 16 — Registrar Stages · Tasks

- [x] Migration `request_checks`, nullable `request_releases.id_presented`; enums `CheckStage`, `CheckItem`, `CheckResult`; model `RequestCheck` (REQ-16-22)
- [x] `RequestWorkflow`: checks, approve / return / decline, resubmit, reopen, payment and final-check decisions, simplified release; remove college, compliance, clarification, bypass and the separate ID review (REQ-16-09..21)
- [x] In progress once forwarded from the first office (REQ-16-18)
- [x] Policy: `evaluate` lock, requester-only `cancel`, `resubmit` (REQ-16-09, 15, 21)
- [x] Working page rebuilt per stage with `x-check-card` (REQ-16-10..14, 16, 17, 19, 20)
- [x] Requester's pending-compliance panel and Resubmit (REQ-16-15)
- [x] Stage queues, Records list and record page, sidebar; remove the Requests list (REQ-16-01..07)
- [x] Remove every CSV download (REQ-16-08)
- [x] Dashboard links, compliance report, demo data
- [x] Superseded criteria marked in specs 04, 05, 07, 11, 15; overview updated
- [x] Pest tests for every REQ-16 criterion; tests of removed behaviour removed
- [x] `CredentialRequest::staffWhereabouts()` on Records, the record page and the Admin dashboard (REQ-16-23)
- [x] Pest tests for REQ-16-23
- [x] Checklist parts' data as `x-summary-row`s, label left and value right (REQ-16-24)
- [x] Pest test for REQ-16-24
- [x] `StageQueue` Payment: payment verification first, then awaiting payment; description names
      both (REQ-16-03)
- [x] Pest test for the changed REQ-16-03
- [x] Payment Validation: "To verify" and "Waiting for the requester (n)" tabs, the awaiting-payment
      requests in the second (REQ-16-03)
- [x] Pest test for the tabbed REQ-16-03
- [x] `RegistrarRequestFilter::matching()` shared by Records and the stage queues; search field on
      every queue (REQ-16-25)
- [x] Pest test for REQ-16-25
- [x] Final quality check without a checklist: remove `CheckStage::FinalCheck` and the items
      `credential_document`, `signatures` (migration deletes their rows); details and route shown
      read-only; Approve needs no parts (REQ-16-19)
- [x] Pest tests for the changed REQ-16-19
- [x] Payment Validation without a checklist or receipt number: remove `CheckStage::Payment` and
      the items `payment_proof`, `payment_amount` (migration deletes their rows); proof and Payment
      Details read-only; Decline, Return to Requester, Approve (REQ-16-17, REQ-04-13)
- [x] Pest tests for the changed REQ-16-17
- [x] Working pages under their queue (`registrar.<queue>.show`), `RegistrarQueue::forRequest()`,
      `RegistrarLink`, redirects from the wrong queue and from `registrar/requests/{…}`, following
      the request after an action, sidebar current item (REQ-16-26)
- [x] Pest tests for REQ-16-26
- [x] Decision last on the working page, after the record; no Proofs of Payment list at Payment
      Validation (REQ-16-27, REQ-16-17)
- [x] Pest tests for REQ-16-27 and the changed REQ-16-17
- [x] No working page under In Progress; a request in progress leads to the searched list (REQ-16-26, see spec 17)
- [x] Search on Release too: remove `RegistrarQueue::isSearchable()` (REQ-16-25)
- [x] Pest test for the changed REQ-16-25
- [x] After a decision, back to the queue's list as left: `ReturnsToList` concern on the
      Registrar, Department and Cashier pages; breadcrumb uses it too (REQ-16-26, REQ-24-06, REQ-25-05)
- [x] Pest tests for the changed REQ-16-26, REQ-24-06, REQ-25-05
- [ ] Release to a representative typed at the counter: `release(..., ?representativeName)`,
      "A representative" always selectable, "Someone else" and the name field, the SPA/ID reminder
      (REQ-16-28, REQ-11-07)
- [ ] Pest tests for REQ-16-28 and the changed REQ-11-07
