# 35 — Admin Override · Tasks

- [x] Spec 35; notes on REQ-05-11, REQ-06-02, REQ-06-21, REQ-14-01, REQ-16-07, REQ-16-09, REQ-16-20,
      REQ-25-05, REQ-32-04, REQ-32-11 and the overview's Admin role
- [ ] Migration: `request_events.override_reason`, `recorded_at`; `RequestEvent`; data dictionary
- [ ] `RequestWorkflow`: `asOverride()`, the override clock, `record()`, `lock()`, `unlockEvaluation()`
- [ ] `RequestEventType::EvaluationUnlocked` (internal), its summary and icon
- [ ] `OverrideStep` enum
- [ ] `RecordOverride` action
- [ ] `CredentialRequestPolicy::override`
- [ ] Records page: `OverridesSteps` concern and the override dialog; `ChoosesRecipient` shared with Release
- [ ] History marks override entries; the activity log adds the reason
- [ ] Notifications: Outgoing by the visit's office; unlock to every registrar
- [ ] Pest tests REQ-35-01..13
- [ ] `composer qa`
