# Assignment 07 — Outgoing submissions handover

Implemented `003_create_submissions.php`, immutable encrypted content/recipient persistence, submission DTO/result contracts, approved-sender and header validation, Bcc-safe durable responses, browser-operation deduplication, same-transaction job enqueue, queued cancellation, attempt-start deadlines, safe send claims, recipient outcomes, terminal states, definitely-not-accepted retry, and uncertain interrupted-send recovery.

The prior zero-byte `003_create_submissions_and_api_keys.php` placeholder was replaced before any migrations were applied. API key/idempotency persistence remains Assignment 10 ownership. No HTTP routes, dependencies, SMTP/IMAP logic, attachments, or broad retry behavior were added.

Run `php tests/Unit/run-submissions.php`, `php tests/Unit/run-jobs.php`, and the opt-in disposable database command in `docs/submissions.md`. This machine has no PHP, Composer, MySQL/MariaDB client, or container runtime, so these tests and migrations are not run. A pinned `php-parser` 3.2.5 syntax parse passed for all related PHP files; it is not PHP runtime evidence. No controlled transport or real-provider checks were possible.

Known blockers: milestones 0–2 are missing; account schema 002 and migrations 003/007 are unapplied; bootstrap/auth/CSRF/migration runner do not exist; no validated content-key configuration loader exists; Assignment 08 must supply normal SMTP handling and call `recoverExpiredSend()` from its expired-lease path.

Exact next step: install/verify PHP 8.4 and a disposable MySQL/MariaDB database, implement the foundation migration runner, run the documented unit/database checks, then wire Assignment 08 transport without changing the conservative retry/uncertain rules.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
