<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Operations Introduction

This scaffold is not deployable as a working mail service. Complete hosting feasibility and installation before exposing it publicly.

## Hosting preflight

Confirm web and CLI PHP 8.4, compatible Composer dependencies, PDO MySQL, Sodium, OpenSSL, mbstring, DOM/XML, fileinfo, and intl. Confirm private writable storage, cPanel database names and grants, one-minute cron, certificate trust, and outbound IMAP 993 and SMTP 465/587. Test SMTP separately from IMAP.

## Deployment requirements

Dedicated subdomain, database credentials, session cookie, API keys, and encryption key. Set the document root to public/ only. Keep all private storage and configuration outside it. Use HTTPS, production-safe errors, explicit migrations, absolute cron paths, and pinned dependencies. Do not reuse the old panel's secret values.

The foundation now provides `php bin/console.php preflight`, `php bin/console.php migrate status`, and `php bin/console.php migrate up`. Preflight checks PHP 8.4, required extensions, Argon2id availability, private writable paths, OpenSSL trust, and a database query; exit 3 identifies a database check failure, 78 invalid configuration, and 1 another failed prerequisite. The migration command returns 64 for misuse and 2 when status finds a changed/missing applied file. Run these with the same PHP binary and environment as cron. No web migration endpoint exists.

Dependencies are declared but no lock file was generated on the current host. On a development host, run and review `composer update --with-all-dependencies`, `composer validate --strict`, and all tests, then commit the lock. Production must use `composer install --no-dev --classmap-authoritative` from that reviewed lock. The public entry point fails with a generic 503 on startup errors. It now registers only the Milestone 2 login/dashboard/password/logout routes; other feature and API routes remain intentionally unwired.

## Administrator provisioning and recovery

After migrations 001 and 006 are applied, provision the only administrator with `php bin/console.php admin:create --email=administrator@example.test`. The command prompts twice without echo; a password is never accepted in argv. For a controlled noninteractive secret pipe, send two identical lines to `php bin/console.php admin:create --email=administrator@example.test --password-stdin`. Use the same pattern with `admin:reset-password` for recovery. The reset increments security version and revokes every session.

Authentication startup also requires separate base64 secrets `MAILPANEL_AUTH_CSRF_KEY` and `MAILPANEL_AUTH_THROTTLE_KEY`; names/defaults are in `docs/configuration.md`. Serve only through HTTPS because cookies are unconditionally Secure. Confirm the real reverse proxy preserves HTTPS and the configured cookie names, but do not trust forwarded IP headers for throttle identity. Monitor 401/403/429 results and safe audit event volume without logging credentials, cookies, form bodies, or raw IP addresses.

Expired/revoked session and throttle cleanup is not implemented. Do not improvise broad deletion against production. Before exposing a restored database, keep traffic disabled, run CLI password recovery to revoke restored sessions, and verify a fresh login. See `docs/authentication.md` for exact tests and failure behavior.

## Worker and recovery requirements

Use database jobs with leases, bounded execution around 40 seconds, per-account locks, heartbeat monitoring, and resumable batches. Prioritize outbound delivery independently of IMAP backfill. Alert visibly if the worker heartbeat is older than five minutes. Record actual cron commands only after implemented.

Back up database, private files, configuration, and encryption key securely. Restore with outbound sending disabled and accounts paused. Review in-flight and backup-gap sends before resuming; never blindly resend historical authentication emails. Test restoration on a separate installation.

## Starting retention

Logs: 30 days. Audit/submission metadata: 90 days. Temporary uploads: 24 hours. API content: encrypted while queued and purged within 24 hours after terminal state. Document provider-side retention separately. Do not auto-delete remote email during cleanup.

Add exact installation, upgrade, rotation, cleanup, backup, restore, and rollback commands as their features are implemented.

## Implemented worker command

After applying migration 007, run the bounded CLI with an absolute PHP 8.4 path. Separate one-minute cron entries should invoke `php bin/worker.php --queue=outbound --budget=40`, `--queue=sync`, and `--queue=maintenance`; use absolute installation paths as shown in `docs/jobs.md`. Overlap is supported by row/account leases. Exit 2 means the database was unavailable and no work was intentionally processed. Keep outbound disabled after restore until leased sends are reconciled. The migration runner and feature handler registrations remain integration prerequisites.

Submission recovery must distinguish `queued` from `sending`. An expired leased job for a `sending` submission becomes `uncertain` and requires operator/provider reconciliation; never update it back to queued manually. A deadline is checked only before an attempt begins. Content-key loss prevents decryption and must produce a visible failure without starting SMTP. Purge terminal private content only through the future bounded maintenance handler and only after backup/retention policy is confirmed.

SMTP operations additionally require `composer install`, PHPMailer 7.1, OpenSSL CA trust, and registered `smtp.send` handler wiring. Repeated authentication failures pause an account at three failures in 15 minutes when `AccountService` receives `JobRepository`. A final-DATA disconnect, stale in-flight lease, or database failure after acceptance is `uncertain`; inspect provider logs and never broadly requeue it.

Send API operation additionally requires PHP Intl, migration 008, a private fingerprint key, request-ID middleware, and exact route registration for `POST /api/v1/mail/send`. Monitor 429/503 rates and idempotency/rate-table cleanup; never log Authorization, idempotency keys, request bodies, or replay responses. Retain replay mappings seven days, including after encrypted terminal content is purged. On restore keep the API and outbound workers disabled, review key revocations and queued/in-flight work, and rotate credentials if backup exposure is suspected.

## SMTP account operational notes

- Back up credential master keys separately from, but together with, the database recovery set. Losing all historical keys makes stored SMTP passwords unrecoverable.
- To rotate keys, add a new key ID and set it active. Keep old keys available until every stored value using them is re-encrypted or removed.
- Disabling an account must stop new submissions and leave queued work visibly paused until later queue rules resolve it.
- A connection test may connect and authenticate but must not send message DATA. Never disable certificate verification to make a test pass.
- Migration 002 and all provider behavior are currently unverified. Do not deploy this scaffold.
