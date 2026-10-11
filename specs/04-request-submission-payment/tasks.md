# 04 — Request Submission, Payment & Registrar Review · Tasks

- [x] Enums: RequestStatus, StepStatus, PaymentStatus, StudentStatus, RequestEventType
- [x] Migrations + models: credential_requests, request_steps, payment_proofs, request_events
- [x] RequestWorkflow service (submit, submitPaymentProof, rejectPayment, approve, changeCollege, cancel)
- [x] Policies for requests and proofs; private proof download route
- [x] Student: new request form, my requests list, request page with payment panel and cancel
- [x] Registrar: requests list with review filter, review page (proofs, college, approve/reject)
- [x] Pest tests for REQ-04-01..15
- [x] `composer qa` green
