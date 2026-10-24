# 03 — Credential Master List & Routes · Requirements

Source: *Proposed Workflow*, section VII (credential types, fees, signatories, processing
periods, routing) and section III-A (requirements shown before submitting).

## User stories

- **US-03-1** As the registrar, I want a master list of the credentials that can be requested,
  with each one's fee, processing period, documentary requirements and special conditions.
- **US-03-2** As the registrar, I want to define each credential's route: the ordered offices it
  passes through and what each one does (prepare, sign, documentary stamp), because routes differ
  per document and may change.
- **US-03-3** As the registrar, I want a Dean step, so that only the requester's own college
  (not every college) reviews and signs.

## Acceptance criteria

### Credential master list
- **REQ-03-01** THE SYSTEM SHALL seed the 17 credential types of the overview's master list with
  their fee, processing period in working days, documentary requirements, special conditions,
  receiving-school requirement and route. Re-seeding SHALL NOT duplicate types or overwrite
  types the registrar has edited.
- **REQ-03-02** THE SYSTEM SHALL let the registrar list credential types with their fee,
  processing period, route and status.
- **REQ-03-03** THE SYSTEM SHALL let the registrar create and edit a credential type with a
  unique name, description, "requires payment" flag, fee (₱, ≥ 0; only kept when payment is
  required), processing period (1–60 working days), documentary requirements (one per line),
  special conditions and whether the receiving school's name is required.
- **REQ-03-04** THE SYSTEM SHALL let the registrar deactivate and reactivate a credential type;
  credential types SHALL NOT be deletable.
- **REQ-03-05** WHILE a credential type has no route steps THE SYSTEM SHALL flag it as
  "No route" in the list (spec 04 does not offer it to requesters).
- **REQ-03-12** WHEN the master list is installed on an existing system THE SYSTEM SHALL create
  or replace the 17 master-list types and their routes, and deactivate the credential types that
  are not on the master list, keeping them for the history of earlier requests.

### Route builder
- **REQ-03-06** THE SYSTEM SHALL let the registrar add a step to a credential type's route by
  choosing an active office or the Dean placeholder, the step's task (prepare, sign or
  documentary stamp) and optional instructions; new steps are appended at the end.
- **REQ-03-07** THE SYSTEM SHALL allow at most one Dean step and SHALL NOT allow the same office
  twice in one route.
- **REQ-03-08** THE SYSTEM SHALL let the registrar move a step up or down and remove a step;
  step positions SHALL stay contiguous (1, 2, 3 …).
- **REQ-03-09** THE SYSTEM SHALL let the registrar edit a step's task and instructions.
- **REQ-03-10** WHEN a route is changed THE SYSTEM SHALL apply it only to requests routed
  afterwards; requests already routed keep their own copy of the steps (spec 05).
- **REQ-03-11** IF a non-registrar opens a credential type or route page or action THEN THE
  SYSTEM SHALL respond 403.
