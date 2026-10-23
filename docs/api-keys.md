<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Never record secrets or private message contents.
Every handover must repeat this requirement for the next contributor.
-->

# Send-only API keys

## Purpose and behavior

`ApiKeyService` issues high-entropy server credentials with the sole `mail:send` scope and an explicit list of allowed `acct_…` sending accounts. The full `mail_sk_…` value exists only in the `IssuedApiKey` creation result and the one-time panel view. The database stores a nonsecret lookup prefix and an Argon2id hash, never the plaintext credential.

Authentication accepts only `Authorization: Bearer …`. Query-string token names are rejected. A valid request receives an `ApiPrincipal` containing the internal key ID, stable public key ID, scope, and allowed account IDs. Assignment 10 must authorize that principal before consulting idempotency state, so revoked keys cannot replay an old response. Admin sessions do not authenticate the API.

Rotation creates a replacement and links it while leaving the old credential active for a deliberate overlap. Revoke the old key after callers change over. Revocation blocks new HTTP calls but does not cancel already durably queued mail; an administrator must separately cancel submissions that are still eligible.

## Schema and installation

Unapplied migration `008_create_api_access.php` creates:

- `api_keys` and `api_key_accounts` for hash-only credentials and account scopes;
- `api_idempotency` for seven-day API replay mappings;
- `api_rate_limit_buckets` for atomic minute/hour counters;
- `api_key_audit_events` with safe event types only.

Migration 008 depends on migrations 002 and 003. It has no down migration and has not been run on MySQL/MariaDB. Back up before applying through the future checksum migration runner. Do not edit an applied 008; use a new additive migration.

## Panel integration

The template `templates/settings/api-keys.twig` contains create and revoke forms with shared CSRF fields and a one-time secret view. The integration owner must add authenticated controller actions and routes through the shared session/CSRF stack. Never put a key in logs, URLs, analytics, browser storage, email, or support screenshots.

## Verification and limitations

Run `php tests/Unit/run-api-keys.php`. It checks hash-only storage, valid/invalid/revoked credentials, account/scope denial, replacement overlap, and continued replacement access. Also run `php tests/Unit/run-unit.php` after integration. These commands have not run here because PHP is unavailable; a PHP parser is only a syntax gate. Database constraints, browser CSRF, migration execution, and HTTP middleware require disposable-database/application tests after the missing foundation is added.

Retention and recovery: retain safe key/audit metadata per policy; keep idempotency mappings seven days even after private content purge. Backups contain password hashes and remain sensitive. Restoring a database may restore previously active keys, so restore with API and outbound processing disabled, review revocations, then rotate credentials as needed.

Troubleshooting: 401 means missing/malformed/invalid/revoked Bearer authentication; 403 is reserved for a valid key lacking account scope. Lost key plaintext cannot be recovered—create a replacement. The exact next integration action is to wire the protected settings controller and `ApiKeyMiddleware`, apply migration 008 to a disposable database, and verify CSRF plus revocation-before-replay.
