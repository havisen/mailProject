<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Project Handover

## Current state

Documentation and a mostly empty starter structure plus isolated SMTP account, database-job, and durable outgoing-submission cores. No end-to-end application feature is usable yet. `public/index.php` remains a minimal static introduction page; routing, bootstrap, authentication, templates, SMTP transport, and the send API are still placeholders. The worker CLI is implemented but cannot process feature work until migrations, configuration, and handlers are integrated.

## Latest changes

Assignments 06 and 07 added migrations 007 and 003, atomic database job/account leases, independent queues, bounded cron runner/heartbeat/checkpoints, handler-owned expired recovery, encrypted immutable outgoing snapshots/recipients, browser operation deduplication, transactional submission/job creation, safe cancellation/claim transitions, recipient outcomes, and conservative uncertain-send recovery. Added `docs/jobs.md`, `docs/submissions.md`, both assignment handovers, and focused unit/disposable-database scripts. The old zero-byte `003_create_submissions_and_api_keys.php` placeholder was replaced before any migration execution; API idempotency remains later API ownership.

The assignment named `00-SHARED-CONTRACT.md`, but that file is absent. The implementation followed `AGENTS.md`, this handover, `ROADMAP.md`, and `docs/workstreams.md`, plus the assignment's explicit migration names.

Integrated the usable SMTP account result from the supplied DeepSeek transcript. Added `CredentialCipher`, `EndpointPolicy`, migration 002, `AccountRepository`, `AccountService`, `SmtpConnectionTester`/`SmtpProbe`, and a dependency-free 40-check module test runner. Added `docs/accounts.md` and `docs/handovers/05-smtp-sending-accounts.md`; updated security, configuration, operations, roadmap, changelog, and sender handover documentation.

The transcript was not copied literally: its Markdown export had corrupted PHP syntax, and its proposed secretbox AAD construction would have used an invalid nonce length. The integrated implementation binds account context through a keyed BLAKE2b-derived secretbox key. The supplied attachment contains one SMTP-account work result; it does not contain implementations for the planned contacts C1–C4 packages, which remain not started.

Earlier planning split the work into `docs/sender-plan.md` (sender/platform S0–S9) and `docs/contacts-deepseek.md` (contacts C1–C4), with ownership and migration reservations in `docs/workstreams.md`.

Repository setup completed on 2026-10-23: initialized local `main`, added a root `.gitignore` covering environment files, Composer dependencies, test output, and private runtime storage, connected `origin` to `https://github.com/havisen/mailProject.git`, preserved the repository's existing initial commit, and pushed the combined history.

The external API is strictly POST /api/v1/mail/send. No read/status, inbox, contact/task, or OTP endpoints are planned. The sender slice does not depend on IMAP and defers drafts, attachments, replies and Sent APPEND. DeepSeek owns contacts only; the sender contributor owns shared authentication/bootstrap and integrates contacts after publishing that contract.

## Architecture and decisions

Read `docs/architecture.md`, `docs/api.md`, `docs/accounts.md`, and `ROADMAP.md`. Target: cPanel, PHP 8.4, MySQL/MariaDB, one administrator, standard IMAP/SMTP providers, cron jobs, and a separate deployment from the old sender. Package compatibility and hosting capabilities remain unverified. The S3 core exists only as isolated code; S0–S2 are still required before integration.

## Environment and configuration

No runtime or hosting environment has been configured. No secrets are present. Existing SMTP names remain `security.credential_keys`, `security.active_key_id`, `mail.approved_endpoints`, `mail.connect_timeout_seconds`, and `mail.operation_timeout_seconds`. New worker CLI names are `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, and `MAILPANEL_DB_PASSWORD`. New unwired submission names are `security.content_keys`, `security.active_content_key_id`, `mail.message_id_domain`, `mail.submission_default_deadline_seconds`, and `mail.submission_maximum_deadline_seconds`. `config/queue.php` contains the implemented 40-second budget, 30-second lease, five-second start margin, recovery limit, and independent queue priorities.

## Database

No schema has been applied and no production database exists. Unapplied migration 002 defines `mail_accounts`; new unapplied 003 defines submissions/encrypted recipients/outcomes; new unapplied 007 defines jobs/account leases/worker runs. Apply in that dependency order through the still-missing migration runner. Do not treat filenames as executed migrations. API idempotency/API-key schema is not included.

## Verification

The SMTP integration was reviewed against the transcript and project contracts. `tests/Unit/run-unit.php` contains 40 account checks. New `tests/Unit/run-jobs.php`, `tests/Unit/run-submissions.php`, and opt-in `tests/Integration/run-jobs-and-submissions.php` cover worker recovery/budgets, validation/privacy/encryption, transactional rollback/deduplication/cancellation/uncertain recovery, and account locking. PHP, Composer, MySQL/MariaDB, and container runtimes are unavailable on this Windows machine, so none of those scripts or migrations ran. A pinned `php-parser` 3.2.5 syntax parse passed for all new/related PHP files; this is not PHP runtime or database acceptance. No HTTP, controlled SMTP fixture, real-provider, hosting, or accessibility checks have run.

Repository preparation checks: reviewed hidden and large files, scanned tracked content for common secret assignments, staged the complete tree, and ran `git diff --cached --check`. No credential values or oversized files were identified. Remote connectivity, history integration, branch tracking, and the initial `main` push were verified.

## Known issues and limitations

The scaffold is deliberately nonfunctional. `composer.json`, application bootstrap, routes, middleware, account controller/templates, and `.htaccess` are still empty. There is no migration runner or dependency container. The real PHPMailer adapter and `smtp.send` handler are missing; `bootstrap/worker.php` has no registrations. Migrations 002/003/007, SQL concurrency, runtime crypto, DNS policy, provider TLS/authentication, HTTP authentication/CSRF, content cleanup, backup, and restore are unverified. Never register a generic SMTP retry: Assignment 08 must claim the submission and call its conservative expired-send recovery. API idempotency remains Assignment 10 and attachments remain Assignment 16.

## Next step

Complete S0 by checking PHP web/CLI, required extensions, private storage, database grants, one-minute cron, certificate trust, and SMTP 465/587. Then implement S1/S2 (Composer/bootstrap, validated configuration, checksum migration runner, administrator sessions, CSRF, and panel shell). Apply migrations 002, 003, and 007 to a disposable database and run all three unit runners plus `tests/Integration/run-jobs-and-submissions.php`. After those pass, wire accounts and Assignment 08's SMTP handler using `SubmissionService::claimForSending()`, terminal/retry methods, and `recoverExpiredSend()`; keep outbound disabled until controlled transport tests pass.

Contacts contributor: no C1–C4 implementation was present in the supplied attachment. Read `docs/contacts-deepseek.md` and `docs/workstreams.md`; begin C1/C2 in isolated contact files and migration 010. Authenticated HTTP verification waits for S1/S2. Do not edit sender/shared platform files. Every contributor updates root `handover.md` and their workstream handover; the integrator merges both factual updates.

## Required instruction for the next contributor

Read handover.md before starting, update it before handing work over, and explicitly tell the next contributor to do the same. Record implementation changes, documentation, configuration names, migrations, checks and results, known issues, decisions, and the exact next step. Never include secret values or private mail contents.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
