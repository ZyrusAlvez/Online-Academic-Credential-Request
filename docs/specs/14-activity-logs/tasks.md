# 14 — Activity Logs · Tasks

- [x] `activity_logs` table, `ActivityLog` model (append-only), `ActivityCategory`; data dictionary (REQ-14-06, 07)
- [x] `RecordActivity` action; request actions from `RequestWorkflow::record()` (REQ-14-01)
- [x] `LogsActivity` trait on setup models, `User` and `IdentityDocument` (REQ-14-02..04, 08)
- [x] Sign-in listener (REQ-14-05)
- [x] Copy existing ledger entries on migrate (REQ-14-14)
- [x] `ActivityLogFilter`, `ActivityLogPolicy`, Logs page and nav item (REQ-14-09..13)
- [x] Pest tests for every REQ-14 criterion
