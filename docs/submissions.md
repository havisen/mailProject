<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Never record secrets or private message contents.
Every handover must repeat this requirement for the next contributor.
-->

# Outgoing submission persistence

## Purpose and current status

The submission service is the shared durable boundary for future panel forms, transport fixtures, and `POST /api/v1/mail/send`. It validates an approved SMTP account, freezes private content, encrypts it, inserts recipients, and enqueues `smtp.send` in one database transaction. Migration `003_create_submissions.php`, DTO/result contracts, `QueuedContentCipher`, `SubmissionValidator`, `SubmissionRepository`, and `SubmissionService` are implemented.

This is not yet a usable sending feature. Foundation/bootstrap, migrations, administrator authentication/CSRF, the API idempotency layer, and SMTP transport handler are missing. No routes were added. The external API remains only the planned send endpoint and still accepts no attachments.

## Creation workflow and interfaces

Construct `SubmissionRequest` with account, source (`panel`, `api`, or controlled `test`), To/Cc/Bcc arrays, subject, text/optional HTML, optional Reply-To/deadline, and a panel browser operation ID. `SubmissionService::create()` owns the transaction and returns `SubmissionResult` only after commit. Database, storage, encryption, disabled/missing-account, and validation failures throw without returning durable acceptance.

For API-layer idempotency, the future API module opens a transaction, writes/locks its API idempotency record, calls `createInCurrentTransaction()`, and commits. That method returns `PendingSubmission`, which must never be serialized as a 202. After commit, call `confirmCommitted()`. This lets one API idempotency record, one submission, and one job commit together while leaving key scoping/conflict policy with Assignment 10.

Panel double-submit protection uses a 16–128 character browser operation ID and a unique `(account_id, source, browser_operation_id)` index. Identical canonical content returns the existing submission; changed content raises a conflict. API idempotency keys are not stored in this schema.

`statusForAuthenticatedUi()` exposes safe Outbox metadata; `detailForAuthenticatedUi()` decrypts the immutable snapshot and recipient outcomes. Both are only for an administrator-authenticated controller and must never be exposed to the external API. `cancelQueued()` atomically transitions only `queued -> canceled` and cancels an unclaimed job. `claimForSending()` row-locks the submission, enforces the deadline as an attempt-start limit, decrypts before changing state, and atomically transitions `queued -> sending`. A cancellation after that point safely fails.

Transport integration uses `finishAttempt()` for `accepted`, `partially_accepted`, `failed`, or `uncertain` and records recipient outcomes separately. `requeueDefinitelyNotAccepted()` is allowed only when the handler asserts SMTP acceptance was impossible and the deadline remains open. Terminal states never requeue. An expired `sending` attempt passed to `recoverExpiredSend()` becomes `uncertain`; an expired job whose submission is still `queued` can safely retry because SMTP was never claimed.

## Validation and privacy

- Sender comes from the enabled account. A requested From must exactly match its approved identity.
- Addresses use strict email validation; CR, LF, and NUL are rejected in headers. Duplicate recipients are removed across To/Cc/Bcc. Account recipient and byte limits apply.
- Bcc remains in encrypted storage for the SMTP envelope and is absent from the durable response. Assignment 08 must omit Bcc from generated message headers.
- API attachments are rejected. An internal panel request may only carry future owned `blob_` references; URLs and filesystem paths are rejected. The blob lifecycle itself belongs to Assignment 16 and is not implemented.
- The snapshot is encrypted with authenticated Sodium secretbox using a submission-specific context. Each recipient address also has contextual encryption. Content keys live outside the database and `public/`.
- `Message-ID` is generated once from the stable `sub_` ID and configured domain. Caller changes after creation cannot alter stored content.
- Safe error categories and outcome enums are stored; raw SMTP responses and message content must not enter logs or error fields.

## Schema and state

Unapplied migration 003 creates `outgoing_submissions`, `submission_recipients`, and `submission_recipient_outcomes`. Account deletion is restricted while submissions exist; recipient/outcome rows cascade only when a submission is deliberately removed. `sent_copy_state` is independent of SMTP state.

State transitions:

```text
queued -> sending -> accepted | partially_accepted | failed | uncertain
queued -> expired | canceled
sending -> queued  (only definitely not accepted, transient, before deadline)
```

Deadlines limit when an attempt may start; they are not delivery promises and do not cancel an accepted SMTP transaction. Mixed recipient acceptance is `partially_accepted` and must not cause already accepted recipients to be sent again.

## Installation, tests, and retention

Apply migrations in order with the future migration runner: 002, 003, then 007. They have not been applied in this repository. Required PHP capabilities are PHP 8.4, PDO MySQL, JSON, and Sodium. Run local risk checks:

```sh
php tests/Unit/run-submissions.php
php tests/Unit/run-jobs.php
```

Run database acceptance only against a disposable database; the command drops module tables:

```sh
MAILPANEL_TEST_DB_DSN='mysql:host=127.0.0.1;dbname=mailpanel_test;charset=utf8mb4' \
MAILPANEL_TEST_DB_USER='test_user' \
MAILPANEL_TEST_DB_PASSWORD='set-outside-shell-history' \
MAILPANEL_TEST_ALLOW_RESET=1 \
php tests/Integration/run-jobs-and-submissions.php
```

Prefer a protected environment file or interactive secret injection instead of literal shell history. The integration runner checks atomic creation/rollback, browser deduplication/conflict, cancellation races, uncertain recovery, and account overlap. It does not contact an SMTP provider.

Queued private content should be purged within 24 hours after a terminal state once the maintenance policy is implemented; retain submission metadata/outcomes for the documented 90 days. Back up content keys separately with the database recovery set. After restore, keep sending paused, reconcile `sending`/leased records and backup-gap sends, and never broadly retry historical mail.

## Known limitations and next integration

No migration runner, dependency wiring, controller, API route, authenticated panel view, cleanup handler, transport handler, or real-provider evidence exists. The future integration owner must instantiate the service from validated configuration, implement Assignment 08's SMTP handler using the claim/finish/recovery interfaces, and implement Assignment 10 API idempotency in its own table/transaction. Do not add a status-reading API.
