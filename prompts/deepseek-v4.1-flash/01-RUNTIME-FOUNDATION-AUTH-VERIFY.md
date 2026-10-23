# Task 01 — runtime, foundation, and administrator verification

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Read `docs/authentication.md`, `docs/configuration.md`, `docs/database.md`, `docs/operations.md`, `docs/security.md`, `docs/testing.md`, `docs/handovers/foundation-runtime.md`, and `docs/handovers/02-administrator-access.md`; inspect Composer/bootstrap/preflight/migrations/auth tests.

Do not add mail features. Establish the missing evidence:

- Confirm PHP 8.4 web/CLI agreement, required extensions including Argon2id, Composer, private writable storage, CA trust, PDO MySQL, database grants, HTTPS, and one-minute cron capability. Do not install system packages or alter hosting without explicit authority; report unavailable prerequisites.
- Resolve reviewed dependencies and create `composer.lock`; run strict Composer validation/security audit where supported.
- With synthetic secrets and a disposable MySQL/MariaDB schema, run preflight; migration status/up/status; repeat/failure/checksum/locking checks; foundation and all auth unit/database/HTTP tests.
- Verify anonymous denial, generic wrong-password response/throttle, login and mutation CSRF, Secure/HttpOnly/SameSite cookies over HTTPS, idle/absolute expiry, password change, CLI reset, session revocation, private-file denial, and safe logs.
- Record exact versions/commands/results. Local checks are not cPanel evidence. Never place secrets in files/history/output.

Accept only when fresh/repeat migrations and authentication failure paths pass. Otherwise leave milestones blocked with the precise resolution step.
