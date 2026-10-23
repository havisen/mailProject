<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Testing Introduction

Most test files are empty placeholders. `tests/Unit/run-unit.php` now contains 40 dependency-free checks for the SMTP account core, but it has not run on the current machine because PHP is unavailable. Add focused tests as real behavior is implemented rather than tests that merely assert the existence of placeholders.

## Critical scenarios

- Fresh install, repeated migrations, private-file access, and administrator recovery.
- Session expiry, CSRF rejection, API scopes, key revocation, and concurrent idempotency.
- Database failure before acceptance, delayed cron, expired queued mail, SMTP rejection, and uncertain acceptance.
- Worker interruption, duplicate-send prevention, separate Sent-copy retry, and safe restore.
- UIDVALIDITY change, arrivals during backfill, remote flag/deletion changes, and interrupted synchronization.
- Multipart and malformed MIME, encoded headers, oversized bodies, HTML injection, trackers, attachment access, and path traversal.
- Reply headers, reply-all recipients, Bcc privacy, draft persistence, and double-click Send.
- Contact/task links surviving cache cleanup and deletion; task due dates in the configured timezone.

Use disposable mailboxes and databases. Run real provider tests against cPanel and a second standard provider before release. Never use private mail or production secrets as fixtures. Record exact commands, results, and unverified areas in handover.md.

## Implemented module checks

- `php tests/Unit/run-jobs.php`: handler execution, checkpoint/timeout budget, explicit sync resume, conservative SMTP recovery, and missing-handler failure.
- `php tests/Unit/run-submissions.php`: immutable normalization, recipient deduplication, header injection, sender authorization, attachment boundaries, deadlines, Bcc-safe response, and contextual encryption.
- `php tests/Integration/run-jobs-and-submissions.php`: opt-in destructive reset of a disposable MySQL/MariaDB schema followed by atomic commit/rollback, browser-operation idempotency/conflict, cancellation race, interrupted-send uncertainty, and account-lease overlap checks. Required variables and exact command are in `docs/submissions.md`.
- `php tests/Unit/run-smtp-delivery.php`: controlled transport-double outcomes for acceptance, partial acceptance, temporary retries, attempt ceiling, DATA uncertainty, disabled accounts, lease recovery, and persistence failure after acceptance.
- `php tests/Unit/run-api-keys.php`: one-time issuance, hash-only persistence, valid/invalid/revoked authentication, sender-account scope, replacement overlap, and explicit revocation.
- `php tests/Unit/run-send-api.php`: field/type/unknown-field validation, IDNA normalization, SMTPUTF8/control rejection, duplicate/recipient/deadline limits, Bcc handling, and stable omitted-deadline canonicalization.
- `php tests/Integration/run-send-api.php`: destructive disposable-schema migration through 008, atomic submission/job/quota/replay commit, identical replay without quota, changed-payload conflict, disabled-account rejection, revocation-before-replay, and database-failure rollback. Use `MAILPANEL_TEST_DB_DSN`, `MAILPANEL_TEST_DB_USER`, `MAILPANEL_TEST_DB_PASSWORD`, and `MAILPANEL_TEST_ALLOW_RESET=1`.

For the API database check, set the same disposable variables shown in `docs/submissions.md`, then run exactly `php tests/Integration/run-send-api.php`. The script drops module tables and must never target production.

None ran on the current Windows machine because PHP, a database client/server, and container runtime are unavailable. These scripts are test doubles/database acceptance only; they are not real SMTP-provider evidence. Neighboring `tests/Unit/run-unit.php` must also run when the PHP environment becomes available.

Assignment 08 still requires a controlled verified-TLS SMTP server for real PHPMailer adapter evidence: RCPT accepted/permanent/transient mixes, DATA accepted/permanent/transient, disconnect during final DATA, timeout, crash, and database outage after acceptance. Use only synthetic fixture addresses/content.

Assignment 10 additionally requires real PSR-7 HTTP tests for every OpenAPI response and two simultaneous database connections/processes for same/different idempotency payloads and rate-limit boundary races. Validate `docs/openapi.yaml` against actual responses. These remain unrun and unverified here.
