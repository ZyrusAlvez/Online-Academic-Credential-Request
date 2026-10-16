# 00 — Product Overview

## Problem

Students and alumni of the University of Perpetual Help System (UPHSL) request academic
credentials (Transcript of Records, certifications, etc.) from the Office of the Registrar.
Each paper request must be signed by several offices before release, and the requester has
no way of knowing where the document currently is or why it is delayed.

## Goal

A web application that acts as a **digital ledger** of every credential request: who asked for
what, which office holds it now, who signed it and when, and what is blocking it.

**Non-goals**

- Transferring credential documents digitally or issuing e-credentials.
- Processing payments. Payment happens on the university's existing portal
  (`https://uphsl.edu.ph/online_payment/guest`); this system only stores the proof of payment.
- Integrating with the student information system (student data is typed on each request).

## Roles

| Role | Who | Can |
|---|---|---|
| `student` | Current students and alumni (self-registered) | Request credentials, upload proof of payment, track their requests, cancel before routing |
| `registrar` | Office of the Registrar staff (admin) | See all requests, verify payments, route/skip/cancel/release, manage departments, credential types, workflows and staff accounts |
| `department` | Staff of a college or office (created by the registrar) | See requests waiting for their office's signature, sign or put on hold with remarks |

## Glossary

| Term | Meaning |
|---|---|
| Department | A college or office that may need to sign a request (e.g. Library, College of Computer Studies) |
| Credential type | A document that can be requested (e.g. TOR); may require payment |
| Workflow | The ordered list of steps a credential type goes through, configured by the registrar |
| Student's College step | A workflow placeholder that resolves to the college the student selected on the request |
| Request step | One department's signature on a specific request (snapshot of the workflow at routing time) |
| Ledger / request event | An append-only record of every status change on a request |
| Reference number | Human-friendly request ID shown to students, e.g. `CR-2026-000042` |

## Seed departments

Colleges (type `college`, Dean's academic verification and approval): Arts and Sciences;
Business and Accountancy; Computer Studies; Criminology; Education; Engineering;
International Hospitality Management; Maritime Education; Law; Graduate School;
Architecture and Aviation.

Offices (type `office`): Library (library clearance); Cashier / Finance Office (financial
clearance); Student Affairs / Student Personnel Services (conduct/clearance verification);
Office of the Registrar (final records verification, processing and release).

The actual workflow is not final, so the registrar can add departments and configure each
credential type's workflow in the app.

## Request lifecycle

```
                       ┌──────── payment rejected ◄────────┐
                       ▼                                   │
(paid)  ──► awaiting_payment ──(proof uploaded)──► payment_review ──(accepted)──┐
                                                                                 ▼
(free)  ──► registrar_review ──────────────────(approved & routed)──────► in_progress
                                                                                 │
          each step in order: pending ⇄ on_hold ──► signed (or skipped by registrar)
                                                                                 │
                                                          (last step signed)     ▼
                                                                    ready_for_release
                                                                                 │
                                                                   (claimed)     ▼
                                                                          released

Any state before released ──► cancelled
  (student: only before in_progress; registrar: any time, reason required)
```

### Request statuses

| Status | Meaning | Student sees |
|---|---|---|
| `awaiting_payment` | Paid credential; no accepted proof yet | "Pay online and upload your receipt" |
| `payment_review` | Proof uploaded, registrar checking | "Payment under review" |
| `registrar_review` | Free credential, registrar checking details | "Under review by the Registrar" |
| `in_progress` | Moving through department steps | "Currently at: *Department*" |
| `ready_for_release` | All steps signed | "Ready for pickup at the Registrar" |
| `released` | Claimed by the student | "Released on *date*" |
| `cancelled` | Stopped, with reason | "Cancelled: *reason*" |

### Step statuses

`waiting` (not reached yet) → `pending` (current, waiting for signature) ⇄ `on_hold`
(department cannot sign yet, remarks required) → `signed` | `skipped` (registrar override,
reason required).

## Cross-cutting rules

- Steps are **strictly sequential**: only one step is `pending`/`on_hold` at a time.
- Only the selected college's step is used; other colleges never see the request.
- Every status change writes a `request_events` row (actor, from → to, remarks, time).
  Events are never edited or deleted.
- Students are notified in-app and by email on every status change of their request.
- Students must verify their email before submitting a request. Deactivated accounts cannot log in.
- Student sign-up accepts any email address (not limited to UPHSL addresses). *(Confirmed by the client.)*
- Students see only office names in their tracking, never the names of the staff who acted. *(Confirmed by the client.)*
- Signing offices, their order and fees per credential are configuration, edited by the registrar
  in the app; the seeded defaults are placeholders until the official process is final.

## Environments

Exactly three environments (`APP_ENV`), each with its own `.env`, database and file storage.
Any other value stops the app at startup.

| Environment | Database | Purpose |
|---|---|---|
| `local` | `uphsl_credential_tracker_local` | A developer's machine; demo data allowed |
| `staging` | `uphsl_credential_tracker_staging` | The server copy for testing changes and demos; demo data allowed |
| `production` | `uphsl_credential_tracker` | Real use; never receives demo or test data |

Settings that follow from the environment (site address, database name, debug mode, log level, mailer, secure
cookies, receipt folder) are defaults in `config/`, so `.env` holds only per-deployment values
and secrets. Local and staging never send real email.

Automated tests run in Laravel's internal `testing` mode on their own database,
`uphsl_credential_tracker_testing`, and refuse to run on any other.

## Feature specs

| # | Feature |
|---|---|
| 01 | Authentication & roles |
| 02 | Departments & staff accounts |
| 03 | Credential types & workflow builder |
| 04 | Request submission, payment & registrar review (incl. routing) |
| 05 | Department signing, hold, skip & release |
| 06 | Tracking & notifications |
| 07 | Registrar console |
