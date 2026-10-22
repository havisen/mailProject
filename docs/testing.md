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

None ran on the current Windows machine because PHP, a database client/server, and container runtime are unavailable. These scripts are test doubles/database acceptance only; they are not real SMTP-provider evidence. Neighboring `tests/Unit/run-unit.php` must also run when the PHP environment becomes available.
