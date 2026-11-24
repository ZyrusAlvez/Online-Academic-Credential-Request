# 35 — Admin Override · Tasks

- [x] Spec 35; notes on REQ-05-11, REQ-06-02, REQ-06-21, REQ-14-01, REQ-16-07, REQ-16-09, REQ-16-20,
      REQ-25-05, REQ-32-04, REQ-32-11 and the overview's Admin role
- [x] Migration: `request_events.override_reason`, `recorded_at`; `RequestEvent`; data dictionary
- [x] `RequestWorkflow`: `asOverride()`, the override clock, `record()`, `lock()`, `unlockEvaluation()`
- [x] `RequestEventType::EvaluationUnlocked` (internal), its summary and icon
- [x] `OverrideStep` enum
- [x] `RecordOverride` action; `CredentialRequest::actingCredential()`, `latestEventId()`
- [x] `CredentialRequestPolicy::override`
- [x] Records page: `OverridesSteps` concern and the override dialog; `ChoosesRecipient` shared with Release
- [x] History marks override entries; the activity log adds the reason
- [x] Notifications: Outgoing by the visit's state (department staff or an override); unlock to every registrar
- [x] Pest tests REQ-35-01..13
- [x] `composer qa`
