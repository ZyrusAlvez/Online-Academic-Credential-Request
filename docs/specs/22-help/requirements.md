# 22 — Help for Requesters · Requirements

Requested by the user on 2026-11-11: a "Help" item in the requester's sidebar that opens into
sub-categories, each a page of frequently asked questions covering everything a requester may find
confusing, such as giving their personal information, academic information and valid ID once
(spec 21). Decided with the user the same day: Help is for requesters only, and it is an expandable
sidebar group whose items each open a page of collapsible questions.

The answers restate how the system works (specs 01, 04, 06, 10, 11, 15, 20, 21); they add no rule
of their own.

## User stories

- **US-22-1** As a requester, I want answers to common questions in the app, grouped by topic, so
  that I can find out what to do without calling the Office of the Registrar.
- **US-22-2** As a requester, I want to understand that my details and ID are verified once and
  what happens when I change them, so that I am not surprised by "For verification".

## Acceptance criteria

### Navigation
- **REQ-22-01** THE requester's sidebar SHALL show, after Request Tracker, an expandable "Help"
  group whose items are the help topics, in this order: Getting Started; Your Details & Valid ID;
  Requesting a Credential; Payment; Tracking & Statuses; Representatives & Release. The group SHALL
  be expanded on a help page, with that topic marked current, and collapsed elsewhere. Staff SHALL
  NOT see it.
- **REQ-22-02** EACH topic SHALL have its own page, `/student/help/<topic>`, titled with the topic
  and a one-line description, listing its questions as collapsible items, closed at first, that
  open with a click, Enter or Space and are announced as expandable by screen readers.
  `/student/help` SHALL open the first topic; an unknown topic SHALL respond 404, and staff 403.

### Content
- **REQ-22-03** THE topics SHALL answer at least:
  - *Getting Started:* what the system does and does not do (the credential is paper and is
    claimed in person; nothing is sent digitally), who can use it, signing up with email or
    Google, the email verification code, a forgotten password, changing the email address, a
    deactivated account, deleting an account.
  - *Your Details & Valid ID:* that personal information, academic information and the valid ID
    are given once and verified once, then reused; what "Verified" and "For verification" mean;
    that details can still be edited and are then verified again on the next request; why a
    submitted request keeps the details it was sent with; the accepted IDs and files; changing the
    saved ID; a rejected ID; who can see the uploads; the data privacy consent.
  - *Requesting a Credential:* the steps of New Request and the tracking number; where to see a
    credential's fee, processing period, requirements and offices; the purpose and "Others"; the
    receiving school; documentary requirement copies; copies of a credential; changing or
    cancelling a request; what happens after submitting (the four evaluation outcomes); an office
    visit.
  - *Payment:* when to pay; how to pay and upload the proof; the amount; what happens after
    uploading; a proof that is not accepted; free credentials.
  - *Tracking & Statuses:* the Request Tracker; what each status means; when the processing
    period starts and how the expected release date is counted; which office holds the document;
    notifications and turning email off; an overdue request.
  - *Representatives & Release:* how the credential is claimed; who may claim or transact for the
    requester; what an SPA is; when a representative can be added; what the representative brings;
    that a representative never uses the requester's account.
- **REQ-22-04** WHERE an answer names a page the requester can open (New Request, Request Tracker,
  Settings → Profile, Settings → Email notifications) THE SYSTEM SHALL link to it. The accepted
  types of ID and the payment portal's address SHALL come from the same settings the forms use, so
  the answers never disagree with them.
- **REQ-22-05** EACH help page SHALL end by saying that anything else can be asked at the Office of
  the Registrar.
