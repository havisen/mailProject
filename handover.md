<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it before handing work over.
Record changes, documentation, configuration names, migrations, tests/results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or private message contents.
Every handover must repeat this requirement for the next contributor, who must follow the same rule.
-->

# Project handover

## Current state and latest changes

The usable DeepSeek result 1 foundation has been integrated after manual reconstruction/review: validated process configuration, private redacted JSON logging, strict MySQL PDO creation, random request IDs, a fail-closed Slim bootstrap, a CLI-only checksum migration runner with advisory locking, preflight/migration commands, and focused tests. It remains unverified because this host has no PHP, Composer, MySQL/MariaDB, Docker, or Podman. DeepSeek result 2 (administrator authentication) was deferred to the next milestone because its draft included a known non-running placeholder test; result 3 was absent from the supplied combined file; result 4 was explicitly an incomplete acceptance skeleton and was not committed as evidence. Details are in `docs/handovers/foundation-runtime.md`.

The repository contains isolated implementations for SMTP accounts and Assignments 06–10, but it is not an end-to-end application and must not be deployed. Assignment 08 added a PHPMailer 7 verified-TLS adapter, separate RCPT/final-DATA evidence, conservative partial/uncertain handling, bounded 1/2/4-minute retries with four attempts, authentication-failure account pausing, and the `smtp.send` handler. Assignment 09 added one-time high-entropy API-key issuance, Argon2id hash-only storage, explicit `mail:send` account scopes, Bearer middleware, revocation/replacement overlap, safe audit events, a settings template, and migration 008. Assignment 10 added the sole external controller/service for `POST /api/v1/mail/send`, strict JSON/recipient/IDNA/deadline validation, HMAC idempotency, request and recipient limits, atomic submission/job/quota/replay persistence, safe errors, OpenAPI, examples, and focused tests.

Long details are in `docs/smtp-delivery.md`, `docs/api-keys.md`, `docs/api.md`, and `docs/handovers/08-smtp-delivery-worker.md`, `09-api-key-management.md`, and `10-send-only-api.md`. The assignment named `00-SHARED-CONTRACT.md`, but that file is absent; work followed `AGENTS.md`, this handover, `ROADMAP.md`, `docs/workstreams.md`, and the supplied assignments.

The external API boundary remains strict: only the POST send endpoint; no status/read/inbox/contact/task/attachment/OTP generation or verification endpoint. The old sender was not modified.

## Files and interfaces

Key new interfaces/classes are `SmtpTransport`, `SubmissionDeliveryStore`, `SendingAccountProvider`, `ApiKeyStore`, `ApiPrincipal`, `ApiKeyService`, `ApiKeyMiddleware`, `SendRequestValidator`, `ApiAccessRepository`, `ApiSendService`, and `SendMailController`. `SubmissionRequest` now accepts structured recipient objects while retaining string recipients for panel callers. `composer.json` declares PHP 8.4, PHPMailer 7.1, PSR HTTP contracts, and required extensions. `config/mail.php`, `config/queue.php`, and `config/api.php` hold nonsecret defaults.

The foundation adds `ConfigLoader`, `PdoFactory`, `MigrationRunner`, `LoggerFactory`, `AppContext`, `RequestIdMiddleware`, bootstrap files, and CLI commands. Feature integration remains intentionally unmodified: `routes/api.php` is still empty. The integration owner must register exactly `POST /api/v1/mail/send` behind `ApiKeyMiddleware`, register `smtp.send` in the worker bootstrap, and protect API-key settings mutations with shared administrator authentication and CSRF. CORS stays disabled.

## Configuration and database

No secrets are present. Required foundation environment names are `APP_ENV`, `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, `MAILPANEL_DB_PASSWORD`, both `MAILPANEL_*_KEYSET` values and active IDs, `MAILPANEL_API_FINGERPRINT_KEY`, and `MAILPANEL_MESSAGE_ID_DOMAIN`; optional paths/TLS/API limit names are listed in `docs/configuration.md`. Keysets remain base64 encoded for the existing ciphers; the distinct API fingerprint secret is decoded once. Secrets stay outside `public/`, the database where specified, logs, and version control.

No migration has been applied. Required order for this slice is 002 accounts, 003 submissions, 007 jobs, then 008 API access. Migration 008 creates API keys/account mappings, seven-day idempotency responses, atomic rate buckets, and safe key audit events. It was coordinated for Assignments 09/10 before application and must not be rewritten after use. There are no down migrations; back up and test restore before production changes.

## Verification and results

Implemented checks are:

- `php tests/Foundation/run-foundation.php`
- `php tests/Integration/run-migrations.php`
- `php tests/Unit/run-unit.php`
- `php tests/Unit/run-jobs.php`
- `php tests/Unit/run-submissions.php`
- `php tests/Unit/run-smtp-delivery.php`
- `php tests/Unit/run-api-keys.php`
- `php tests/Unit/run-send-api.php`
- `php tests/Integration/run-jobs-and-submissions.php`
- `php tests/Integration/run-send-api.php`

The integration scripts require a disposable MySQL/MariaDB schema and the variables documented in `docs/testing.md`; they destructively reset module tables. PHP, Composer, MySQL/MariaDB, Docker, Podman, and a mail fixture are unavailable on this host, so none of the PHP/runtime/database/SMTP/HTTP checks ran. A pinned PHP parser syntax gate passed across all 76 nonempty PHP files, `composer.json` parsed, OpenAPI YAML parsed and contained only the intended POST path, and diff checks passed except expected line-ending warnings. These static checks are not runtime acceptance or full OpenAPI behavioral validation.

Unverified: Composer resolution, all migrations and concurrency semantics, Argon2id/Sodium at runtime, actual PSR-7 middleware/controller behavior, two-connection idempotency/rate races, controlled SMTP RCPT/DATA/timeout/crash cases, real-provider/cPanel TLS and limits, storage exhaustion, cleanup/retention, backup/restore, authenticated panel CSRF, accessibility, and deployment.

## Known issues and decisions

The foundation is present but unverified and dependencies are neither installed nor locked. Administrator session/CSRF and feature route/container/worker wiring still block completion. `public/index.php` now fails closed and exposes no feature route. The API returns no status URL and SMTP cannot guarantee exactly-once delivery. Revocation blocks new/replay HTTP authorization but does not cancel previously queued submissions. Expired/in-flight or post-acceptance-persistence sends become uncertain and never automatically resend. Bcc is encrypted/envelope-only. API content has no attachments. Restore must start with API/outbound disabled and queued/in-flight work reconciled.

## Exact next step

Install PHP 8.4/Composer and required extensions, resolve/review dependencies into `composer.lock`, verify private storage/database grants/cron/CA trust/SMTP connectivity, and run the new foundation and disposable migration commands. Record exact outputs. Then implement Milestone 2 administrator auth/session/CSRF as a separate reviewable change. Only after that passes, wire the route, middleware, services, and worker handler; apply 002/003/007/008 to a disposable database; run every listed check plus real two-process concurrency and controlled verified-TLS SMTP fixtures; validate actual HTTP responses against `docs/openapi.yaml`. Keep outbound and the public API disabled until those checks pass.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
