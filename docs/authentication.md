<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Administrator authentication

## Status and boundary

Milestone 2 implements one independent administrator, CLI creation and recovery, browser login/logout/password change, database sessions, action-bound CSRF, database login throttling, protected routing, and safe audit events. It does not add signup, email recovery, multiple administrators, roles, API authentication, account-management pages, or mail features. The code is statically parse-checked but has not run on PHP/MySQL on the current host, so it is not accepted for deployment yet.

Routes are `GET|POST /login`, protected `GET /`, protected `GET|POST /settings/password`, and protected `POST /logout`. Anonymous HTML reads redirect to `/login`; unauthenticated protected mutations return 401. Password change and logout require the session middleware followed by action-specific CSRF validation. Feature routes added later must explicitly add the same authentication middleware and CSRF middleware for every browser mutation.

## Installation and commands

Prerequisites are PHP 8.4 CLI/web with PDO MySQL, Sodium, OpenSSL, mbstring, Intl and the Composer packages in `composer.json`; MySQL 8.0+ or MariaDB 10.6+; HTTPS; a document root of `public/`; and all required environment values from `docs/configuration.md`.

After Composer dependencies and a disposable database are available:

```text
php bin/console.php preflight
php bin/console.php migrate status
php bin/console.php migrate up
php bin/console.php migrate status
php bin/console.php admin:create --email=administrator@example.test
```

The administrator command prompts twice with terminal echo disabled. For controlled noninteractive provisioning, pass two identical newline-terminated values through standard input and add `--password-stdin`; never put a password in arguments, shell history, documentation, or logs. Only one administrator can be created. Offline recovery is:

```text
php bin/console.php admin:reset-password --email=administrator@example.test
```

Reset increments the administrator security version and revokes every existing session. Exit 64 means command misuse, 78 means invalid configuration, and 1 means validation/database/operational failure. Database details are deliberately not printed.

## Authentication and session behavior

Passwords are valid UTF-8, 14 or more characters, no more than 1,024 bytes, and may not contain NUL/CR/LF. They are hashed with PHP Argon2id. Login errors are generic; unknown users still perform a password verification against an existing or dummy Argon2id hash. A successful login revokes the supplied prior session token, creates a fresh random 256-bit token, and persists only its SHA-256 hash.

Session cookies default to `mailpanel_admin` and always use `Secure`, `HttpOnly`, `SameSite=Strict`, and `Path=/`. Sessions expire after 30 minutes idle and 12 hours absolute by default. The database stores last-seen and both expiries; middleware checks the administrator security version and disabled state on every protected request. Password change verifies the current password, requires a different confirmed new password, revokes all sessions, and creates one replacement session.

Login uses a separate HttpOnly challenge cookie (`mailpanel_login_csrf`) bound into a short-lived signed token. Authenticated forms use the random per-session CSRF secret. Tokens are bound to one action and expiry; invalid/missing tokens return 403 without executing the mutation. All rendered authentication/private pages use `Cache-Control: no-store`. Global middleware adds a restrictive CSP, frame denial, no-referrer, MIME sniffing prevention, permissions restrictions, and HSTS.

## Throttling and audit

Failures are counted independently for normalized email and source IP using HMAC-SHA-256 scope identifiers derived with `MAILPANEL_AUTH_THROTTLE_KEY`; raw email/IP values are not stored in throttle rows. Defaults are five failures in 15 minutes followed by a 15-minute block. A blocked login returns 429 and `Retry-After`. State changes use short database transactions and row locks.

Audit rows allow only these event names: `administrator_created`, `administrator_password_reset`, `login_succeeded`, `login_failed`, `login_throttled`, `logout`, `password_changed`, `session_expired`, and `csrf_rejected`. They contain safe references, request ID, optional administrator ID, and an HMAC IP hash; never passwords, tokens, request bodies, private messages, authorization values, or provider transcripts.

## Schema, retention, backup, and recovery

Unapplied migration `001_create_admin_and_jobs.php` creates `administrators`, `administrator_sessions`, and `administrator_login_throttles`. A singleton unique/check constraint enforces one administrator. Session tokens are hash-only. Unapplied migration `006_create_audit_events.php` creates `audit_events` with a nullable administrator foreign key. These files have not been applied and must not be edited after application; use additive migrations.

Session and throttle cleanup is not implemented in this milestone. Until bounded maintenance exists, expired/revoked rows accumulate and must not be deleted with an ad-hoc broad production statement. The database backup contains password hashes, CSRF secrets, session-token hashes, and security metadata and remains sensitive. Restore behind HTTPS with public traffic disabled, reset the administrator password using CLI, and treat restored sessions as revoked before exposure. Retain audit events under the project 90-day metadata policy once cleanup is implemented.

## Verification

Dependency-free behavior check:

```text
php tests/Unit/run-auth.php
```

Disposable database acceptance (destructively drops only the named authentication tables):

```text
MAILPANEL_TEST_ALLOW_RESET=1 MAILPANEL_TEST_DB_DSN='mysql:host=127.0.0.1;dbname=mailpanel_auth_test;charset=utf8mb4' MAILPANEL_TEST_DB_USER='test_user' MAILPANEL_TEST_DB_PASSWORD='test_password' php tests/Integration/run-auth.php
```

Actual Slim/middleware/controller acceptance uses the same variables:

```text
MAILPANEL_TEST_ALLOW_RESET=1 MAILPANEL_TEST_DB_DSN='mysql:host=127.0.0.1;dbname=mailpanel_auth_test;charset=utf8mb4' MAILPANEL_TEST_DB_USER='test_user' MAILPANEL_TEST_DB_PASSWORD='test_password' php tests/Integration/run-auth-http.php
```

Use only a disposable schema and synthetic values. The database test covers singleton creation, Argon2id storage, wrong-password throttling, hash-only sessions, idle/absolute expiry, password-change invalidation, and CLI-style recovery. The HTTP test covers anonymous redirect, login-CSRF failure, successful login, dashboard access, password-change CSRF/success/session replacement, logout-CSRF failure, successful logout, and revoked-session rejection. The current machine lacks PHP, Composer, and MySQL/MariaDB, so these commands have not run. Real HTTPS cookie behavior, proxy/web-server configuration, concurrency at throttle boundaries, retention cleanup, and backup/restore remain unverified.

## Troubleshooting and exact next step

Startup now fails if `MAILPANEL_AUTH_CSRF_KEY` or `MAILPANEL_AUTH_THROTTLE_KEY` is missing/weak, private directories are absent, Composer dependencies are absent, or the database/migrations are unavailable. A login loop commonly indicates HTTP instead of HTTPS, a wrong cookie name/path, an unapplied migration, a changed security version, or an expired session. Do not weaken cookie flags or CSRF to diagnose it.

Exact next step: install PHP 8.4 and Composer, generate/review `composer.lock`, start a disposable MySQL/MariaDB database, run preflight and migrations twice, run all three authentication checks plus neighboring foundation tests, then verify Secure-cookie login/logout/password recovery over HTTPS before marking Milestone 2 complete.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
