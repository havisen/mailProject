<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Configuration Introduction

`ConfigLoader` now validates the foundation process environment before web or CLI startup. Nonsecret defaults remain in `config/*.php`; secrets are injected by the host outside `public/` and version control. No `.env` file loader is included. Never include real values in documentation.

## Foundation environment names

Required: `APP_ENV`, `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, `MAILPANEL_DB_PASSWORD`, `MAILPANEL_CREDENTIAL_KEYSET`, `MAILPANEL_CREDENTIAL_ACTIVE_KEY_ID`, `MAILPANEL_CONTENT_KEYSET`, `MAILPANEL_CONTENT_ACTIVE_KEY_ID`, `MAILPANEL_API_FINGERPRINT_KEY`, `MAILPANEL_AUTH_CSRF_KEY`, `MAILPANEL_AUTH_THROTTLE_KEY`, and `MAILPANEL_MESSAGE_ID_DOMAIN`. `APP_ENV` is one of `production`, `staging`, `development`, or `test`; `APP_DEBUG=true` is rejected in production.

Optional: `APP_DEBUG` (default false), `PRIVATE_STORAGE_PATH` (default `<project>/storage`), `LOG_PATH` (default `<storage>/logs`), `MAILPANEL_TLS_CA_BUNDLE`, the authentication names below, `MAILPANEL_API_MAXIMUM_BODY_BYTES` (262144), `MAILPANEL_API_REQUESTS_PER_MINUTE` (30), `MAILPANEL_API_RECIPIENTS_PER_HOUR` (100), and `MAILPANEL_IDEMPOTENCY_RETENTION_SECONDS` (604800). Paths must be absolute, existing directories; private paths and traversals into `public/` are rejected. Create them with restrictive hosting permissions before startup.

Both keyset values are JSON objects mapping an identifier to a base64-encoded 32-byte random value. Active IDs must exist in their respective keyset. `MAILPANEL_API_FINGERPRINT_KEY`, `MAILPANEL_AUTH_CSRF_KEY`, and `MAILPANEL_AUTH_THROTTLE_KEY` are three separate base64 values representing at least 32 random bytes each. Do not reuse one purpose's secret for another. The loader preserves cipher keyset encoding for `CredentialCipher`/`QueuedContentCipher` and decodes these standalone secrets once. Generate secrets on a trusted PHP host with `base64_encode(random_bytes(32))`; store the result in the hosting secret configuration, never in this repository or logs.

## Administrator authentication configuration

`MAILPANEL_AUTH_CSRF_KEY` signs action/session/expiry-bound browser CSRF tokens. `MAILPANEL_AUTH_THROTTLE_KEY` HMACs login identifiers, IP addresses, and audit IP values so raw values are not stored. Back both up as deployment secrets; rotation invalidates existing CSRF tokens or changes throttle/audit correlations but does not alter password hashes.

Optional validated values:

- `MAILPANEL_SESSION_IDLE_SECONDS`: default 1800, range 300–86400.
- `MAILPANEL_SESSION_ABSOLUTE_SECONDS`: default 43200, range 900–604800 and greater than the idle timeout.
- `MAILPANEL_SESSION_COOKIE_NAME`: default `mailpanel_admin`, 3–64 safe cookie-name characters.
- `MAILPANEL_LOGIN_CSRF_COOKIE_NAME`: default `mailpanel_login_csrf`, same syntax and different from the session name.
- `MAILPANEL_CSRF_TTL_SECONDS`: default 900, range 60–3600.
- `MAILPANEL_LOGIN_MAX_FAILURES`: default 5, range 2–100.
- `MAILPANEL_LOGIN_WINDOW_SECONDS`: default 900, range 60–86400.
- `MAILPANEL_LOGIN_BLOCK_SECONDS`: default 900, range 60–86400.

Password policy is intentionally code-owned at 14 characters and 1,024 UTF-8 bytes so an environment change cannot silently weaken it. Cookies are always Secure/HttpOnly/SameSite=Strict; there is no configuration switch to weaken them.

## Required categories

- Application URL, environment, timezone, private storage paths, and session settings.
- Database host, port, database name, username, and secret.
- Credential-encryption key and key version, stored separately from the database.
- Approved mail provider endpoints and explicit narrow exceptions for legitimate private cPanel endpoints.
- Per-account SMTP/IMAP settings, sender identities, Sent-copy mode, and limits.
- Worker budgets, synchronization windows, upload limits, retention, and API limits.

## Initial defaults

One-minute cron; one IMAP connection per account; initial target up to five accounts; newest 200 Inbox messages first; backfill up to 90 days with a visible 5,000-record cap per account. Other folders synchronize when enabled. List pages contain 50 messages, maximum 100. Network connection timeout 10 seconds, operation timeout 20 seconds.

Attachment limit 10 MiB per file, outgoing MIME limit 20 MiB or lower provider limit, decoded rendered body limit 2 MiB. Account for MIME encoding overhead. Session idle timeout 30 minutes, absolute timeout 12 hours. Store timestamps in UTC; display with administrator timezone. Limits must be visible rather than silently truncating or skipping content.

## SMTP account configuration names

Names only; real values must not be committed.

- `security.credential_keys`: map of key ID to base64-encoded 32-byte master key.
- `security.active_key_id`: key used for new credential encryption; it must exist in `credential_keys`.
- `mail.approved_endpoints`: operator-owned list of `host`, optional pinned `port`/`tls`, and an exceptional `allow_private` flag.
- `mail.connect_timeout_seconds`: default 10 seconds.
- `mail.operation_timeout_seconds`: default 20 seconds.

Per-account limits are stored in `mail_accounts`: `max_recipients`, `max_hourly_recipients`, and `max_outbound_bytes`. Configuration loading and validation remain part of sender package S1.

## Job worker configuration

`config/queue.php` defines `run_budget_seconds` (40), `lease_seconds` (30), `minimum_start_seconds` (5), `recovery_limit` (25), and priorities for the independent `outbound`, `sync`, and `maintenance` queues. CLI database names are `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, and `MAILPANEL_DB_PASSWORD`. Values are deployment secrets and must be injected outside `public/` and version control. See `docs/jobs.md`; feature handlers must use remaining-budget-aware network timeouts.

## Submission configuration

Dependency wiring provides content-key and message-domain values through the validated foundation loader. Content keys are distinct configuration from SMTP credential/authentication keys, remain outside the database/document root, and must be backed up securely. Account rows provide `max_recipients` and `max_outbound_bytes`.

`config/mail.php` now defines delivery defaults: connection timeout 10 seconds, operation timeout 20 seconds, retry delays 60/120/240 seconds, maximum four attempts, and account pause after three authentication failures within 900 seconds. Worker time remaining always lowers network timeouts. TLS verification has no disable switch.

API keys need no shared plaintext-key configuration: each key is randomly generated and stored only as an Argon2id hash. Migration 008 stores its nonsecret prefix, label, `mail:send` scope, and allowed account mappings. The separate server-held API fingerprint secret and request/recipient limits are now validated by the foundation loader; do not reuse encryption keys for that purpose.

## Send API configuration

`config/api.php` defines `maximum_body_bytes` (262144), `requests_per_minute_per_key` (30), `accepted_recipients_per_hour_per_account` (100), and `idempotency_retention_seconds` (604800). Dependency wiring must provide `security.api_fingerprint_key` as at least 32 random bytes decoded from secret configuration outside `public/` and version control. It is an HMAC key for low-entropy idempotency identifiers/payloads and must not reuse credential/content keys. Per-account hourly limits may lower the global 100-recipient default. CORS remains disabled.
