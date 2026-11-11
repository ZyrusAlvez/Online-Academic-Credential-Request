# 04 — Submission, Evaluation, Compliance, Payment & Acceptance · Tasks

- [x] Reset migration: rebuild the request tables; new enums and models
- [x] `RequestWorkflow`: submit, evaluation outcomes, compliance, customer action, payment, acceptance, cancel (REQ-04-03, 05..17)
- [x] New-request form with credential details, notice, profile snapshot (REQ-04-01, 02)
- [x] Requester request page: compliance notice, clarification reply, payment (REQ-04-09, 11, 12)
- [x] Registrar request page: evaluation, compliance, payment and acceptance panels (REQ-04-05..08, 10, 13..15)
- [x] Pest tests for every REQ-04 criterion
- [x] Credential picker is a dropdown; the details panel (with description, fee and period badges)
      appears once a credential is chosen (REQ-04-02)
- [x] Drop `copies`: form, workflow, views, CSV, factory, seeders; migration resets unpaid amounts (REQ-04-01..03)
- [x] Pest tests for the changed REQ-04-01..03
- [x] Migration: `request_documents.requirement`; `DocumentKind::Requirement`;
      `CredentialRequest::requirementDocuments()` (REQ-04-19)
- [x] `RequestWorkflow::attachRequirementDocuments()`; New Request's per-requirement file fields,
      checked on choice and on review, sent on submit (REQ-04-18)
- [x] `<x-requirement-document-list>` on the evaluation checklist, Records and the owner's page (REQ-04-19)
- [x] Pest tests for REQ-04-18, 19
- [x] Tracking number `CRYY-NNNNN` from `CredentialRequest::trackingNumber()`, shared by the
      workflow and the factory; older numbers stay (REQ-04-03)
- [x] Pest tests for the changed REQ-04-03
- [x] Tracking number prefix in lowercase: `cr26-00042` (REQ-04-03)
- [ ] Each chosen requirement file's name on New Request links to the file viewer (REQ-04-18)
- [ ] Pest test for it
