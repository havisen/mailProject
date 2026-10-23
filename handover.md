<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it before handing work over.
Record changes, documentation, configuration names, migrations, tests/results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or private message contents.
Every handover must repeat this requirement for the next contributor, who must follow the same rule.
-->

# Project handover

## Current state and latest changes

Milestone 2 administrator access is now implemented as a fresh reviewed module on top of the usable DeepSeek result 1 foundation. It adds one-administrator CLI creation/recovery, Argon2id passwords, generic login failures, database email/IP throttling, fresh hash-only versioned sessions, 30-minute idle/12-hour absolute expiry, Secure/HttpOnly/SameSite cookies, login and authenticated CSRF, password change/logout, protected Slim routes, security/no-store headers, and safe audit events. Details, exact commands, and unresolved evidence are in `docs/authentication.md` and `docs/handovers/02-administrator-access.md`.

The foundation provides validated process configuration, private redacted JSON logging, strict MySQL PDO creation, random request IDs, fail-closed Slim bootstrap, CLI-only checksum migrations with advisory locking, and preflight/migration commands. It remains unverified because this host has no PHP, Composer, MySQL/MariaDB, Docker, or Podman. The authentication implementation is therefore `blocked`, not complete or deployable.

The repository contains isolated implementations for SMTP accounts and Assignments 06–10, but it is not an end-to-end application and must not be deployed. Assignment 08 added a PHPMailer 7 verified-TLS adapter, separate RCPT/final-DATA evidence, conservative partial/uncertain handling, bounded 1/2/4-minute retries with four attempts, authentication-failure account pausing, and the `smtp.send` handler. Assignment 09 added one-time high-entropy API-key issuance, Argon2id hash-only storage, explicit `mail:send` account scopes, Bearer middleware, revocation/replacement overlap, safe audit events, a settings template, and migration 008. Assignment 10 added the sole external controller/service for `POST /api/v1/mail/send`, strict JSON/recipient/IDNA/deadline validation, HMAC idempotency, request and recipient limits, atomic submission/job/quota/replay persistence, safe errors, OpenAPI, examples, and focused tests.

Long details are in `docs/authentication.md`, `docs/smtp-delivery.md`, `docs/api-keys.md`, `docs/api.md`, and their matching `docs/handovers/` files. The assignment named `00-SHARED-CONTRACT.md`, but that file is absent; work followed `AGENTS.md`, this handover, `ROADMAP.md`, `docs/workstreams.md`, and the supplied assignments.

The external API boundary remains strict: only the POST send endpoint; no status/read/inbox/contact/task/attachment/OTP generation or verification endpoint. The old sender was not modified.

## Files and interfaces

Key new interfaces/classes are `SmtpTransport`, `SubmissionDeliveryStore`, `SendingAccountProvider`, `ApiKeyStore`, `ApiPrincipal`, `ApiKeyService`, `ApiKeyMiddleware`, `SendRequestValidator`, `ApiAccessRepository`, `ApiSendService`, and `SendMailController`. `SubmissionRequest` now accepts structured recipient objects while retaining string recipients for panel callers. `composer.json` declares PHP 8.4, PHPMailer 7.1, PSR HTTP contracts, and required extensions. `config/mail.php`, `config/queue.php`, and `config/api.php` hold nonsecret defaults.

The authentication boundary adds `AuthService`, four prepared repositories, `PasswordPolicy`/`PasswordHasher`, `CsrfTokenManager`, `SecureCookieManager`, authentication/CSRF/security-header middleware, `AuthController`, three DTOs, CLI management, templates, and unit/database/HTTP checks. `routes/web.php` registers only `GET|POST /login`, protected `GET /`, protected `GET|POST /settings/password`, and protected+CSRF `POST /logout`. `routes/api.php` remains empty. The integration owner must register exactly `POST /api/v1/mail/send` behind `ApiKeyMiddleware`, register `smtp.send` in the worker bootstrap, and protect all future browser mutations with this administrator authentication and action-specific CSRF. CORS stays disabled.

## Configuration and database

No secrets are present. Required foundation environment names now also include separate base64 `MAILPANEL_AUTH_CSRF_KEY` and `MAILPANEL_AUTH_THROTTLE_KEY` values of at least 32 random bytes. Optional session/cookie/CSRF/throttle names and bounds are in `docs/configuration.md`. Keysets remain base64 encoded for existing ciphers; standalone secrets are decoded once and must not be reused. Secrets stay outside `public/`, the database where specified, logs, and version control.

No migration has been applied. Migration 001 creates the singleton administrator, hash-only sessions, and HMAC throttle rows; migration 006 creates fixed-shape audit events. Sender dependency order remains 002 accounts, 003 submissions, 007 jobs, then 008 API access. Never rewrite any migration after application; use additive migrations. There are no down migrations; back up and test restore before production changes.

## Verification and results

Implemented checks are:

- `php tests/Foundation/run-foundation.php`
- `php tests/Unit/run-auth.php`
- `php tests/Integration/run-auth.php`
- `php tests/Integration/run-auth-http.php`
- `php tests/Integration/run-migrations.php`
- `php tests/Unit/run-unit.php`
- `php tests/Unit/run-jobs.php`
- `php tests/Unit/run-submissions.php`
- `php tests/Unit/run-smtp-delivery.php`
- `php tests/Unit/run-api-keys.php`
- `php tests/Unit/run-send-api.php`
- `php tests/Integration/run-jobs-and-submissions.php`
- `php tests/Integration/run-send-api.php`

The integration scripts require a disposable MySQL/MariaDB schema and the variables documented in `docs/testing.md`; they destructively reset module tables. `Get-Command php,composer,mysql,docker,podman` confirmed all five are unavailable on this host, so none of the PHP/runtime/database/SMTP/HTTP checks ran. A repository-wide pinned Node `php-parser` gate reported `Parsed 102 nonempty PHP files; failures: 0`; Node JSON parsing reported `composer.json parsed`; `git diff --check` found no whitespace errors (only expected LF-to-CRLF working-copy warnings). These static checks are not runtime acceptance or full OpenAPI behavioral validation.

Unverified: Composer resolution, all migrations and concurrency semantics, Argon2id/Sodium at runtime, actual PSR-7 middleware/controller behavior, Secure-cookie behavior behind HTTPS, throttle races, session/throttle cleanup, two-connection idempotency/rate races, controlled SMTP RCPT/DATA/timeout/crash cases, real-provider/cPanel TLS and limits, storage exhaustion, backup/restore, accessibility, and deployment.

## Known issues and decisions

The foundation and administrator boundary are present but unverified and dependencies are neither installed nor locked. `public/index.php` fails closed; it exposes only the authentication routes described above. Expired/revoked session and throttle cleanup is not implemented. Forwarded IP headers are intentionally ignored; deployment must use the actual server `REMOTE_ADDR` boundary. The API returns no status URL and SMTP cannot guarantee exactly-once delivery. Revocation blocks new/replay HTTP authorization but does not cancel previously queued submissions. Expired/in-flight or post-acceptance-persistence sends become uncertain and never automatically resend. Bcc is encrypted/envelope-only. API content has no attachments. Restore must start with public/API/outbound disabled, CLI password recovery, and queued/in-flight reconciliation.

## Exact next step

Install PHP 8.4/Composer and required extensions, resolve/review dependencies into `composer.lock`, provision a disposable MySQL/MariaDB schema, and run preflight plus migration status/up/status. Run foundation tests and all three authentication checks, then verify login, expiry, password change/recovery, cookies, and CSRF through real HTTPS; record exact outputs and fix failures before marking Milestone 2 complete. Only afterward integrate future feature routes behind the shared middleware, register the worker handler/API route, apply 002/003/007/008 to a disposable database, and run the remaining concurrency/SMTP/OpenAPI checks. Keep outbound and the public API disabled until those checks pass.

For delegated implementation, `DEEPSEEK-V4.1-FLASH-OUTBOUND-INTEGRATION.md` is a concise repository-local prompt covering authenticated account management, durable submissions/jobs, SMTP worker registration, the sole send API, and manual Send/Outbox in sequential gates. It requires prerequisite checks and must not be used to claim blocked runtime/provider evidence.

The ordered prompt pack in `prompts/deepseek-v4.1-flash/` covers prerequisite verification and steps 9–17. Each run loads one small task plus shared rules and stops after one milestone/sub-package, minimizing context and preserving dependency order. Its index also points to the existing outbound integration prompt for steps 4–8.

`prompts/deepseek-v4.1-flash/RUN-ONE-AND-REPORT.md` is the monitoring wrapper for delegated runs. Give it exactly one target prompt; DeepSeek must stop after the first incomplete package and return the standardized evidence packet. Review the packet together with the working-tree diff before allowing the next package. The packet does not replace runtime, database, controlled-provider, or deployment evidence.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
