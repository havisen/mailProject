<!-- PROJECT HANDOVER RULE: Read and update handover.md before handoff; never record secrets or private content; require the next contributor to do the same. -->

# Assignment 10 handover

Implemented the isolated send-only API contract: strict JSON/recipient/IDNA/deadline validation, 256 KiB limit, account authorization before replay, HMAC-protected canonical idempotency, independent request abuse counting, atomic account quota/submission/job/replay persistence, original 202 replay, safe errors, Retry-After, controller, OpenAPI, examples, and unit/disposable-database scripts. No API attachments or other endpoints were added.

Changed files include `config/api.php`; `composer.json`; API DTO/error/validator classes; `ApiAccessRepository`; `ApiSendService`; `SendMailController`; structured-recipient support in submission contracts; `tests/Unit/run-send-api.php`; `tests/Integration/run-send-api.php`; OpenAPI/examples and related architecture/configuration/database/operations/security/submission/testing docs.

Run `composer install`, apply migrations 002/003/007/008 through the future migration runner, `php tests/Unit/run-send-api.php`, all neighboring unit runners, and `php tests/Integration/run-send-api.php` with the disposable DB variables documented in `docs/testing.md`. No command ran here because PHP, Composer, MySQL/MariaDB, and containers are unavailable. The syntax parser is not runtime evidence. No HTTP server, concurrent two-connection race, OpenAPI validator, controlled SMTP, real provider, or cPanel test has run.

Migration 008 remains unapplied. Configuration names are `security.api_fingerprint_key`, `api.maximum_body_bytes`, `api.requests_per_minute_per_key`, `api.accepted_recipients_per_hour_per_account`, and `api.idempotency_retention_seconds`; the fingerprint secret must remain outside the database, `public/`, logs, and version control.

Known integration issue: `routes/api.php` and the missing dependency/bootstrap layer still need the integration owner to register exactly `POST /api/v1/mail/send` behind request-ID and API-key middleware. CORS must remain disabled. No 202 may be returned before commit. `00-SHARED-CONTRACT.md` is still absent.

Exact next step: build the shared PHP/bootstrap/migration runner, register the one route, apply migrations to a disposable MySQL/MariaDB schema, run all listed checks including real two-connection idempotency/rate races, and compare actual HTTP responses to `docs/openapi.yaml` before enabling outbound work.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
