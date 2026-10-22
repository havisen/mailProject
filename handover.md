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

Documentation and a mostly empty starter structure plus the platform-independent SMTP sending-account core. No end-to-end application feature is usable yet. `public/index.php` remains a minimal static introduction page; routing, bootstrap, authentication, templates, workers, and the send API are still placeholders.

## Latest changes

Integrated the usable SMTP account result from the supplied DeepSeek transcript. Added `CredentialCipher`, `EndpointPolicy`, migration 002, `AccountRepository`, `AccountService`, `SmtpConnectionTester`/`SmtpProbe`, and a dependency-free 40-check module test runner. Added `docs/accounts.md` and `docs/handovers/05-smtp-sending-accounts.md`; updated security, configuration, operations, roadmap, changelog, and sender handover documentation.

The transcript was not copied literally: its Markdown export had corrupted PHP syntax, and its proposed secretbox AAD construction would have used an invalid nonce length. The integrated implementation binds account context through a keyed BLAKE2b-derived secretbox key. The supplied attachment contains one SMTP-account work result; it does not contain implementations for the planned contacts C1–C4 packages, which remain not started.

Earlier planning split the work into `docs/sender-plan.md` (sender/platform S0–S9) and `docs/contacts-deepseek.md` (contacts C1–C4), with ownership and migration reservations in `docs/workstreams.md`.

Repository setup began on 2026-10-23: initialized a local Git repository on `main` and added a root `.gitignore` covering environment files, Composer dependencies, test output, and private runtime storage. The GitHub remote is not yet configured because the exact owner/repository URL has not been verified.

The external API is strictly POST /api/v1/mail/send. No read/status, inbox, contact/task, or OTP endpoints are planned. The sender slice does not depend on IMAP and defers drafts, attachments, replies and Sent APPEND. DeepSeek owns contacts only; the sender contributor owns shared authentication/bootstrap and integrates contacts after publishing that contract.

## Architecture and decisions

Read `docs/architecture.md`, `docs/api.md`, `docs/accounts.md`, and `ROADMAP.md`. Target: cPanel, PHP 8.4, MySQL/MariaDB, one administrator, standard IMAP/SMTP providers, cron jobs, and a separate deployment from the old sender. Package compatibility and hosting capabilities remain unverified. The S3 core exists only as isolated code; S0–S2 are still required before integration.

## Environment and configuration

No runtime or hosting environment has been configured. Config files and `.env.example` remain empty. No secrets are present. New documented names, without values: `security.credential_keys`, `security.active_key_id`, `mail.approved_endpoints`, `mail.connect_timeout_seconds`, and `mail.operation_timeout_seconds`.

## Database

No schema has been applied and no production database exists. Migration `002_create_accounts.php` now defines the SMTP-only `mail_accounts` table but has not been executed. Other reserved migration placeholders remain empty. Do not treat filenames as executed migrations.

## Verification

The SMTP integration was reviewed against the transcript and project contracts. `tests/Unit/run-unit.php` contains 40 checks covering cipher behavior, endpoint restrictions, account-service behavior, and safe SMTP result classification. PHP and Composer are not installed or available on `PATH` on this Windows machine, so PHP lint and the test runner were not executed. No database, HTTP, provider, hosting, or accessibility checks have run.

Repository preparation checks: reviewed hidden and large files, scanned tracked content for common secret assignments, staged the complete tree, and ran `git diff --cached --check`. No credential values or oversized files were identified. Remote connectivity and push remain unverified.

## Known issues and limitations

The scaffold is deliberately nonfunctional. `composer.json`, bootstrap, routes, middleware, account controller/templates, and `.htaccess` are still empty. There is no migration runner or dependency container. The real PHPMailer `SmtpProbe` adapter is missing. Migration 002, repository SQL, runtime crypto, DNS policy, real-provider TLS/authentication, and account HTTP/CSRF flows are unverified. Backup and recovery behavior remains unimplemented.

The GitHub remote URL is still required. GitHub CLI is unavailable and the accessible browser session is signed out, so the private repository cannot be identified reliably by name alone.

## Next step

Configure `origin` using the exact GitHub repository URL and push `main` when that URL is available. Complete S0 by checking PHP web/CLI, required extensions, private storage, database grants, one-minute cron, certificate trust, and SMTP 465/587. Then implement S1/S2 (Composer/bootstrap, configuration, migration runner, tests, administrator sessions, CSRF, and panel shell). After that, apply migration 002 and wire the S3 account core into a controller, protected routes, templates, navigation, dependency container, and a PHPMailer probe. Run the 40 module checks and disposable MySQL/MariaDB integration tests before claiming S3 accepted.

Contacts contributor: no C1–C4 implementation was present in the supplied attachment. Read `docs/contacts-deepseek.md` and `docs/workstreams.md`; begin C1/C2 in isolated contact files and migration 010. Authenticated HTTP verification waits for S1/S2. Do not edit sender/shared platform files. Every contributor updates root `handover.md` and their workstream handover; the integrator merges both factual updates.

## Required instruction for the next contributor

Read handover.md before starting, update it before handing work over, and explicitly tell the next contributor to do the same. Record implementation changes, documentation, configuration names, migrations, checks and results, known issues, decisions, and the exact next step. Never include secret values or private mail contents.
