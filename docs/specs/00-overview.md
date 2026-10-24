# 00 — Product Overview

Source of truth: the client's **Proposed Workflow and Process Flow for the Capstone System**
(`docs/Proposed-Workflow-and-Process-Flow-for-the-Capstone-System.docx`, October 2026). This
overview restates it as the system's target model; the feature specs below implement it in
phases. Where the document leaves a rule open, the assumption taken is listed under
[Assumptions](#assumptions).

## Problem

Students, graduates and former students of the University of Perpetual Help System Laguna
(UPHSL) request academic credentials (Transcript of Records, certifications, diplomas, CAV…)
from the Office of the Registrar. Each credential passes through several offices and
signatories before release. Requesters cannot see where their document is, and the Registrar
cannot tell which office is holding it, for how long, or why.

## Goal

A web application that acts as a **digital ledger** of every credential request: who asked for
what, whether the requirements and payment are complete, which office holds the document now,
every receipt, release and return between offices, and how long each step took, so the
Registrar can find bottlenecks and measure on-time release.

**Non-goals**

- Transferring credential documents digitally or issuing e-credentials. The paper credential
  never leaves the offices digitally.
- Processing payments. Payment happens on the university's existing portal
  (`https://uphsl.edu.ph/online_payment/guest`); this system stores the proof and records its
  verification.
- Integrating with the student information system (academic details are typed by the requester
  and checked by the evaluator against the university's records).
- Predictions or machine learning.

**Uploads** (all private, staff-only): the requester's valid ID, a representative's Special Power
of Attorney (SPA) and the IDs of the owner and representative, and the proof of payment.

## The six phases

1. **Online registration and request.** Create an account, verify the email, upload a valid ID,
   enter academic information, choose the credential and state its purpose.
2. **Records and requirements evaluation.** The Registrar's evaluator checks identity, academic
   records, admission requirements, graduation status and clearance.
3. **Payment verification and acceptance.** Staff verify the payment against the official record
   and formally accept the request. **The processing clock starts here.**
4. **Document routing and processing.** The credential moves through its required offices;
   each office accepts receipt, then approves and releases it to the next office, or returns it
   for correction.
5. **Final approval and release.** The Registrar's final quality check, the release
   notification, verification of the claimant (owner or authorized representative) and the
   release record.
6. **Performance analysis and reporting.** On-time and overdue rates, processing time, time per
   office, causes of delay.

**Core principle:** the date a request is *submitted* and the date it is *accepted for
processing* are different. The prescribed processing period, and so the due date, starts only
at acceptance.

## Roles

| Role | Who | Can |
|---|---|---|
| `student` (shown as *Requester*) | Current students, graduates and former students (self-registered) | Register and upload an ID, request credentials, declare a representative, upload proof of payment, track requests, cancel before acceptance |
| `registrar` | Office of the Registrar staff | Everything on the Registrar's side: verify identities, evaluate requests, record compliance, verify payments, accept, act on the Registrar's routing steps (preparation, final quality check), release, manage offices, credential types, the working calendar and staff accounts, view reports. One role for all Registrar staff; every action records the staff member. |
| `department` | Staff of a signing office: a college (the Dean), VP Finance, Chancellor, President | Accept receipt of documents sent to their office, approve and release them to the next office, return them for correction, record delay reasons |

## Glossary

| Term | Meaning |
|---|---|
| Office (department) | A college (Dean) or an office (Registrar, VP Finance, Chancellor, President) on a credential's route |
| Credential type | A document that can be requested, with its fee, processing period, documentary requirements, special conditions and route (the *credential master list*) |
| Route | The ordered offices a credential type passes through, each with a task: prepare, sign, documentary stamp, final check. The same office may appear more than once (the Registrar first and last). |
| Dean step | A route placeholder that resolves to the college the requester belongs to |
| Route step | One entry of a request's route, copied from the credential type at acceptance |
| Office visit | One physical stay of the document at an office: forwarded → received → completed or returned. A return and re-send create a new visit; earlier visits are never overwritten. |
| Deficiency | A missing requirement found during evaluation (a document, clearance or other), resolved when the Registrar records its physical submission |
| Representative | A person acting for the document owner, backed by an SPA and the IDs of both |
| Accepted date | When the Registrar formally accepted the request; the processing period starts here |
| Due date | Accepted date plus the processing period in working days. Never changed; approved pauses produce a separate *adjusted* due date. |
| Working day | Monday to Saturday, except days in the working calendar (holidays, closures) |
| Ledger / request event | An append-only record of every action on a request |
| Tracking number | Human-friendly request ID shown to requesters, e.g. `CR-2026-000042` |

## Offices and credential master list

Offices: the 11 colleges (each acting as the **Dean**: Arts and Sciences; Business and
Accountancy; Computer Studies; Criminology; Education; Engineering; International Hospitality
Management; Maritime Education; Law; Graduate School; Architecture and Aviation), the **Office of
the Registrar**, the **VP Finance** (documentary stamp, no signature), the **Chancellor** and the
**President**. Library, Cashier and Student Affairs clearance is checked by the evaluator as a
requirement, not as a routing step.

Initial credential master list (the registrar can edit all of it). Every route ends with the
Registrar's final quality check.

| Credential | Fee | Signatories | Processing period |
|---|---|---|---|
| Authentication | ₱50 | University Registrar | 1 working day |
| Certification, Authentication, Verification (CAV) | ₱500 | University Registrar | 2 working days |
| Certification – general; of Graduation; of Enrollment; of Units Earned; Course Description; Medium of Instruction; Copy of Grades; Notice of Acceptance; Letter for the BI Commissioner; Permit to Cross-Enroll; Transfer Certificate; Copy of Grades for Transfer Credentials | ₱230 each | University Registrar, VP Finance (documentary stamp), Chancellor | 6 working days |
| Diploma / Certificate for Non-Degree Programs | ₱800 | University Registrar, Chancellor, President | 6 working days |
| Transcript of Records (TOR); TOR for Transfer Credentials | ₱530 | University Registrar, Dean, VP Finance (documentary stamp), Chancellor | 7 working days |

Transfer credentials require the complete name of the receiving school. Other or combined
transfer credential requests are *to be confirmed* by the University and are out of scope.

Example route (TOR): Registrar *prepare* → Dean *sign* → VP Finance *documentary stamp* →
Chancellor *sign* → Registrar *final check*.

## Request lifecycle

```
submitted ──► under_evaluation ──┬─(complete)──────────────► awaiting_payment ──► payment_verification
                 ▲    │          ├─(incomplete)──► pending_compliance ─(resolved)─┘        │    │
                 │    │          ├─(clarify)───► returned_for_customer_action ─(responded)─┘    │
                 │    │          └─(not eligible)─► declined                     (rejected)◄────┘
                 │    │                                                                  │ (verified)
                 └────┘ (customer responds)                                              ▼
                                                                          accepted_for_processing
                                                                           (clock starts, due date)
                                                                                         │ first receipt
                                                                                         ▼
                 returned_for_correction ◄──(office returns)──── in_progress ◄──(re-received)
                                                                                         │ Registrar receives
                                                                                         ▼ for final check
                                                                                for_final_approval
                                                                                         │ final QC done
                                                                                         ▼
                                                                                 ready_for_release
                                                                                         │ claimant verified
                                                                                         ▼
                                                                                      released

Any open status ──► cancelled  (requester: before acceptance; registrar: any time, reason required)
```

### Request statuses

| Status | Meaning |
|---|---|
| `submitted` | Online request received |
| `under_evaluation` | Academic records and requirements are being reviewed |
| `pending_compliance` | The requester must submit missing documents or complete clearance in person |
| `returned_for_customer_action` | The requester must clarify or act before processing continues |
| `awaiting_payment` | Requirements complete; payment instructions provided |
| `payment_verification` | Proof of payment is awaiting verification |
| `accepted_for_processing` | All conditions satisfied; the processing period has started |
| `in_progress` | The credential is being prepared or processed by the required offices |
| `returned_for_correction` | An office returned the document to an earlier office for correction |
| `for_final_approval` | Back at the Registrar for the final quality check |
| `ready_for_release` | Processing complete; available for claiming |
| `released` | Released to the owner or an authorized representative |
| `declined` | Not eligible; reason recorded |
| `cancelled` | Cancelled, reason recorded |

A requester's return (`pending_compliance`, `returned_for_customer_action`) and an internal
return between offices (`returned_for_correction`) are different events, statuses and report rows.

### Office actions

Every office has the same buttons:

- **Accept receipt.** Confirms the document physically arrived; records the office, employee
  and time. Forwarding a document never counts as its receipt.
- **Approve / release to next office.** Records completion of the office's task (prepared,
  signed, stamped, checked) and forwards the document to the next office on the route.
- **Return for correction.** Sends the document back to an earlier office with the reason and the
  corrective action needed; once corrected, it is forwarded back and received again.
- **Record delay reason.** A category and explanation, for the delay analysis.
- **View transaction history.** Every receipt, release, return, resubmission and approval.

## Due dates and the working calendar

- Working days are Monday to Saturday, minus the days in the working calendar (holidays,
  special non-working days, office closures), which the registrar maintains.
- Due date = accepted date + the credential's processing period in working days. It is never
  changed. A formally approved pause produces a separate adjusted due date; both are shown.
- Time spent at an office = working days from receipt to completion (receipt day excluded,
  completion day included; the same day counts as 0).

## Cross-cutting rules

- Every action writes a `request_events` row (actor, from → to, remarks, time). Events are never
  edited or deleted. Office visits are never overwritten either.
- Requesters are notified in-app and by email when the request is received, when requirements or
  clarification are needed, when payment instructions are available, when the payment is verified
  and the request accepted, when the credential is ready for release, and when it becomes overdue.
- Requesters must verify their email before submitting a request. Deactivated accounts cannot log in.
- Sign-up accepts any email address (not limited to UPHSL addresses). *(Confirmed by the client.)*
- Requesters see only office names in their tracking, never the names of the staff who acted.
  *(Confirmed by the client.)*
- A representative never uses the owner's account as the owner: their identity and authority are
  recorded separately, and a credential is not released until the claimant is verified.
- Uploaded IDs, SPAs and payment proofs are private: only registrar staff (and the uploader for
  their own files) can open them.
- Routes, fees, processing periods, requirements and the working calendar are configuration,
  edited by the registrar in the app.

## Assumptions

Rules the document leaves open, taken as follows until the client says otherwise:

1. The fee is per copy (amount due = fee × copies).
2. On-time release is measured against the adjusted due date; the original due date is always
   shown alongside.
3. Requesters see an internal *returned for correction* as "In progress".
4. The Registrar's final quality check is inside the processing period.
5. A credential is *unclaimed* once it has been ready for release for more than 10 working days.
6. Payment verification records the official receipt (OR) number.
7. A registrar's action approves a due-date pause.
8. Working days are Monday to Saturday (the document's own example counts Monday to Friday; the
   work week is a one-line setting).

## Environments

Exactly three environments (`APP_ENV`), each with its own `.env`, database and file storage.
Any other value stops the app at startup.

| Environment | Database | Purpose |
|---|---|---|
| `local` | `uphsl_credential_tracker_local` | A developer's machine; demo data allowed |
| `staging` | `uphsl_credential_tracker_staging` | The server copy for testing changes and demos; demo data allowed |
| `production` | `uphsl_credential_tracker` | Real use; never receives demo or test data |

Settings that follow from the environment (site address, database name, debug mode, log level,
secure cookies, receipt folder) are defaults in `config/`, so `.env` holds only per-deployment
values and secrets.

Staging behaves like production; the only intended differences are:

- **Data:** local and staging may hold demo data (`app:reset-demo`); production never does.
- **Errors:** error details (debug mode) are shown on local and staging, never in production.

Every environment works the same way in everything else:

- **Email** is sent through Gmail whenever its credentials are configured, and written to the log
  file otherwise. On local, every email is redirected to the developer's own inbox. Mail to
  reserved example domains (`example.com`, `.test`, …), which demo accounts use, is never sent.
- **The first registrar** comes from `INITIAL_REGISTRAR_EMAIL` and `INITIAL_REGISTRAR_PASSWORD`
  (REQ-01-15).
- **Installed packages** are the same: no development tools on the server.

Automated tests run in Laravel's internal `testing` mode on their own database,
`uphsl_credential_tracker_testing`, and refuse to run on any other.

## Feature specs

All feature specs describe this model.

| # | Feature | Phase |
|---|---|---|
| 01 | Registration, identity & roles | done |
| 02 | Offices & staff accounts | done |
| 03 | Credential master list & routes | done |
| 04 | Submission, evaluation, compliance, payment & acceptance | done |
| 05 | Office routing: receipt, release, returns & final check | done |
| 06 | Tracking, notifications & reminders | done |
| 07 | Performance dashboard & reports | done |
| 08 | ~~Turnaround prediction~~ (removed; the system makes no predictions) | — |
| 09 | UX polish | done |
| 10 | Working calendar & due dates | done |
| 11 | Representatives & release | done |
