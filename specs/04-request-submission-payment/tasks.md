# 04 — Request Submission, Payment & Registrar Review · Tasks

- [ ] Enums: RequestStatus, StepStatus, PaymentStatus, StudentStatus, RequestEventType
- [ ] Migrations + models: credential_requests, request_steps, payment_proofs, request_events
- [ ] RequestWorkflow service (submit, submitPaymentProof, rejectPayment, approve, changeCollege, cancel)
- [ ] Policies for requests and proofs; private proof download route
- [ ] Student: new request form, my requests list, request page with payment panel and cancel
- [ ] Registrar: requests list with review filter, review page (proofs, college, approve/reject)
- [ ] Pest tests for REQ-04-01..15
- [ ] `composer qa` green
