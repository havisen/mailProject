<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Handover — SMTP Sending Accounts

## Delivered

- Versioned Sodium credential encryption with key IDs, random nonces, and account/field context binding.
- Exact approved-host policy, 465/587 TLS rules, DNS resolution, and special/private address blocking.
- Migration 002 for the SMTP-only `mail_accounts` table.
- Prepared-statement account repository with panel-safe reads.
- Account validation, create/edit/disable/delete behavior, password preservation on blank edit, and worker-only decryption.
- Transport-independent SMTP connection tester with fixed safe result categories.
- A dependency-free 40-check test runner and account/security/operations documentation.

The attached DeepSeek transcript was not copied literally. Its code formatting was corrupted, and its proposed secretbox AAD construction supplied an invalid nonce length. The integrated cipher keeps secretbox and binds context by deriving a context-specific key with keyed BLAKE2b.

## Configuration and migration

Names only; no values are committed:

- `security.credential_keys`
- `security.active_key_id`
- `mail.approved_endpoints`
- `mail.connect_timeout_seconds`
- `mail.operation_timeout_seconds`

Migration 002 is written but has not been applied. No database or migration runner exists in the scaffold.

## Verification

Static file review completed. `tests/Unit/run-unit.php` contains 40 module checks. Runtime lint/tests could not run because neither PHP nor Composer is installed or available on `PATH` on the current machine.

Not verified: MySQL/MariaDB migration and repository behavior, HTTP/auth/CSRF integration, dependency wiring, a PHPMailer probe, real DNS/TLS/provider authentication, test-email delivery, or accessibility.

## Integration requests

1. Complete S0 and record the target host's actual PHP/extensions, Composer, SMTP egress, certificate trust, and provider restrictions.
2. Complete S1/S2: Composer/bootstrap, migration runner, dependency container, tests, administrator session, CSRF, and protected panel layout.
3. Apply migration 002, wire an `AccountController`, mount authenticated account routes, and implement the existing account templates.
4. Add a PHPMailer `SmtpProbe` adapter that verifies certificates and never sends DATA.
5. Replace or incorporate the temporary test runner in the shared PHPUnit harness and test on disposable MySQL/MariaDB.
6. Exercise a controlled provider authentication check; send any test email only through the later durable submission queue.

The next contributor must read and update `handover.md` before handing over, record exact checks and results, and pass this instruction onward. Never include secrets or private mail contents.
