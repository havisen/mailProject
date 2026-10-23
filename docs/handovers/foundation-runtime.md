<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it before handing work over.
Record changes, documentation, configuration names, migrations, tests/results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or private message contents.
Every handover must repeat this requirement for the next contributor, who must follow the same rule.
-->

# Foundation runtime handover

## Purpose and current status

This change integrates the usable foundation portion of the supplied DeepSeek results after reconstructing and reviewing the transcript. It adds strict environment configuration, MySQL PDO creation, private structured logging with redaction, Twig with safe rendering defaults, a checksum migration runner with an advisory overlap lock, request IDs, a fail-closed Slim entry point, and runnable migration/preflight commands. It does not integrate administrator authentication or the send route. The application remains non-deployable until dependencies are locked, migrations run against a disposable database, authentication is implemented, and feature wiring and acceptance checks pass.

The DeepSeek authentication result was deferred because Milestone 2 depends on this foundation and its proposed test suite included a known non-running placeholder. No distinct result 3 was present in the supplied file. Result 4 described an incomplete acceptance harness with a placeholder SMTP fixture and was not added as evidence.

## Interfaces and changed behavior

- `ConfigLoader::fromEnvironment()` validates all startup settings and fails before the web application or commands run.
- `PdoFactory::create()` enables exceptions, native prepared statements, strict SQL mode, and UTC sessions.
- `MigrationRunner::up()` applies ordered nonempty PHP migrations, records SHA-256 checksums after success, rejects edits/missing applied files, and serializes runners with `GET_LOCK`. It never runs from an HTTP route.
- `php bin/console.php migrate status|up` and `php bin/console.php preflight` are real CLI entry points. Exit 64 is command misuse, 78 is invalid configuration, 3 is preflight database failure, and 1 is another runtime failure.
- `public/index.php` now boots Slim and returns a generic 503 if startup fails. No public application or API route is registered yet; unmatched requests are 404.
- Internal request IDs are random and are returned as `X-Request-ID`; inbound values are not trusted.

## Configuration

Required process environment names are `APP_ENV`, `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, `MAILPANEL_DB_PASSWORD`, `MAILPANEL_CREDENTIAL_KEYSET`, `MAILPANEL_CREDENTIAL_ACTIVE_KEY_ID`, `MAILPANEL_CONTENT_KEYSET`, `MAILPANEL_CONTENT_ACTIVE_KEY_ID`, `MAILPANEL_API_FINGERPRINT_KEY`, and `MAILPANEL_MESSAGE_ID_DOMAIN`. Optional names are `APP_DEBUG`, `PRIVATE_STORAGE_PATH`, `LOG_PATH`, `MAILPANEL_TLS_CA_BUNDLE`, `MAILPANEL_API_MAXIMUM_BODY_BYTES`, `MAILPANEL_API_REQUESTS_PER_MINUTE`, `MAILPANEL_API_RECIPIENTS_PER_HOUR`, and `MAILPANEL_IDEMPOTENCY_RETENTION_SECONDS`.

Keysets are JSON objects whose values are base64-encoded 32-byte values. The loader deliberately preserves those encoded values because the existing cipher services decode them. The fingerprint key is separate base64 material of at least 32 bytes and is decoded by the loader. Do not store values in the repository, under `public/`, in logs, or in this handover.

## Install, run, and migration state

On a development host with PHP 8.4 and Composer, first resolve and review dependencies because this host could not create `composer.lock`:

```text
composer update --with-all-dependencies
composer validate --strict
php bin/console.php preflight
php bin/console.php migrate status
php bin/console.php migrate up
php bin/console.php migrate status
```

Production installation must use the reviewed committed lock with `composer install --no-dev --classmap-authoritative`. Set the document root to `public/` only. Migration state remains unapplied. Empty reserved migrations are skipped; once populated they become pending. MySQL/MariaDB DDL may auto-commit, so a failed migration is not automatically rolled back and must never be recorded as applied; inspect and repair it before rerunning.

## Tests and evidence

Commands added:

```text
php tests/Foundation/run-foundation.php
php tests/Integration/run-migrations.php
```

The foundation runner checks encoded cipher key compatibility, decoded fingerprint keys, inherited queue/API defaults, redaction, missing configuration, production debug rejection, short keys, and public storage rejection. The integration runner requires a disposable schema plus `MAILPANEL_TEST_DB_DSN`, `MAILPANEL_TEST_DB_USER`, `MAILPANEL_TEST_DB_PASSWORD`, and `MAILPANEL_TEST_ALLOW_RESET=1`; it destructively resets only its probe tables and the migration ledger, then checks overlap locking, apply-once behavior, checksums, and failed-ledger behavior.

PHP, Composer, MySQL/MariaDB, Docker, and Podman are absent on the authoring host. Runtime, dependency resolution, database migration, web, cron, TLS, and provider checks were therefore not run. A pinned `php-parser` 3.2.5 syntax pass parsed all 76 nonempty PHP files with zero failures, PowerShell parsed `composer.json`, and `git diff --check` found no whitespace errors (only existing line-ending conversion notices). An attempted OpenAPI YAML recheck could not run because Python PyYAML is absent; the OpenAPI file was not changed in this milestone and its earlier recorded static result remains in root `handover.md`. These are not runtime evidence.

## Security, recovery, and decisions

Logs are JSON under the private log path and redact sensitive keys, bearer values, bodies, and content fields. Startup and HTTP failures do not expose configuration or database errors. No health route was added because the external surface is intentionally narrow. Migrations are CLI-only. No migration or private data was deleted or applied.

Back up the database, private files, and encryption-key material together but store key material separately from database backups. A changed applied migration is an operator error: restore the original file or add a new migration; never update the ledger checksum to conceal it.

## Integration requests and exact next step

The next contributor must install PHP 8.4/Composer and required extensions, resolve and review dependencies into `composer.lock`, run the foundation and disposable migration checks, and record exact outputs. If those pass, implement Milestone 2 administrator authentication in a separate reviewable change. After authentication passes, integrate feature dependencies, exactly one `POST /api/v1/mail/send` route, and the `smtp.send` worker handler; do not expose any read/status/contact/task/OTP API.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
