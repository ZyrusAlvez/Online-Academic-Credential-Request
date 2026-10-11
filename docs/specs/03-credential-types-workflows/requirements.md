# 03 — Credential Types & Workflow Builder · Requirements

## User stories

- **US-03-1** As the registrar, I want to maintain the list of credentials students can
  request, including whether each needs payment.
- **US-03-2** As the registrar, I want to define, per credential type, the ordered list of
  offices that must sign it, because the official flow is not final and differs per document.
- **US-03-3** As the registrar, I want a "Student's College" step, so that only the
  requester's own college (not every college) signs.

## Acceptance criteria

### Credential types
- **REQ-03-01** THE SYSTEM SHALL seed common credential types with default workflows:
  Transcript of Records (paid), Certificate of Enrollment, Certificate of Grades, Good Moral
  Certificate, Honorable Dismissal / Transfer Credentials (paid), Diploma – Certified True Copy
  (paid), and CAV – Certification, Authentication and Verification (paid). Re-seeding SHALL NOT
  duplicate types or overwrite workflows the registrar has edited.
- **REQ-03-02** THE SYSTEM SHALL let the registrar list credential types with their payment
  requirement, fee, processing days, number of workflow steps and status.
- **REQ-03-03** THE SYSTEM SHALL let the registrar create and edit a credential type with a
  unique name, description, "requires payment" flag, fee (optional, ₱, ≥ 0; only kept when
  payment is required) and processing days (1–60).
- **REQ-03-04** THE SYSTEM SHALL let the registrar deactivate and reactivate a credential type;
  credential types SHALL NOT be deletable.
- **REQ-03-05** WHILE a credential type has no workflow steps THE SYSTEM SHALL flag it as
  "No workflow" in the list (spec 04 does not offer it to students).

### Workflow builder
- **REQ-03-06** THE SYSTEM SHALL let the registrar add a step to a credential type's workflow
  by choosing an active department or the "Student's College" placeholder, with optional
  instructions for the signing office; new steps are appended at the end.
- **REQ-03-07** THE SYSTEM SHALL allow at most one "Student's College" step and SHALL NOT allow
  the same department twice in one workflow.
- **REQ-03-08** THE SYSTEM SHALL let the registrar move a step up or down and remove a step;
  step positions SHALL stay contiguous (1, 2, 3 …).
- **REQ-03-09** THE SYSTEM SHALL let the registrar edit a step's instructions.
- **REQ-03-10** WHEN a workflow is changed THE SYSTEM SHALL apply it only to requests routed
  afterwards; requests already routed keep their own copy of the steps (spec 05).
- **REQ-03-11** IF a non-registrar opens a credential type or workflow page or action THEN THE
  SYSTEM SHALL respond 403.
