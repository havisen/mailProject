<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Milestone 2 handover — administrator access

## Delivered

Implemented a single-administrator authentication boundary: Argon2id password creation/reset, generic login failures, database-backed email/IP throttling, fresh hash-only database sessions, idle/absolute expiry, security-version revocation, login and authenticated CSRF, password change, logout, protected Slim routes, private cache/security headers, safe audit events, CLI management, and focused unit/database/HTTP tests.

The routes are `GET|POST /login`, protected `GET /`, protected `GET|POST /settings/password`, and protected+CSRF `POST /logout`. The module deliberately does not expose API auth, signup, email recovery, roles, mail/account pages, or other roadmap features. The old sender was untouched.

## Files and interfaces

Core interfaces are `AuthService`, `AdministratorRepository`, `AdministratorSessionRepository`, `LoginThrottleRepository`, `AuditRepository`, `PasswordPolicy`, `PasswordHasher`, `CsrfTokenManager`, `SecureCookieManager`, `AuthMiddleware`, `CsrfMiddleware`, `SecurityHeadersMiddleware`, and `AuthController`. DTOs are under `src/Auth/`; request IP/user-agent/request-ID extraction is in `RequestMetadata` and does not trust forwarded headers.

Migration 001 now creates `administrators`, `administrator_sessions`, and `administrator_login_throttles`; migration 006 creates `audit_events`. Neither is applied. Templates implement login, dashboard, and password change. `bootstrap/dependencies.php`, `bootstrap/web.php`, and `routes/web.php` register only this milestone's panel behavior.

## Configuration

New required secrets: `MAILPANEL_AUTH_CSRF_KEY` and `MAILPANEL_AUTH_THROTTLE_KEY`, each base64 for at least 32 random bytes. Optional validated names/defaults are documented in `docs/configuration.md`: session idle/absolute durations, both cookie names, CSRF TTL, and throttle limit/window/block duration. Defaults are 30-minute idle, 12-hour absolute, 15-minute CSRF, five failures in 15 minutes, and 15-minute blocking.

## Verification and limitations

Added `tests/Unit/run-auth.php`, `tests/Integration/run-auth.php`, and `tests/Integration/run-auth-http.php`; the exact commands are in `docs/authentication.md`. A repository-wide pinned Node `php-parser` gate reported `Parsed 102 nonempty PHP files; failures: 0`; `composer.json` parsed as JSON and `git diff --check` found no whitespace errors. PHP/Composer/MySQL are unavailable on the current machine, so no runtime/database/HTTP acceptance ran. Migrations are unapplied. Secure-cookie behavior over HTTPS, throttle races, cleanup, and restore are unverified. Do not deploy this repository.

## Exact next step

Install PHP 8.4/Composer and required extensions; create/review `composer.lock`; provision a disposable database; run preflight, migration status/up/status, all foundation/authentication checks, and real HTTPS browser checks; record exact output. Fix any failure using additive schema changes if migrations have already been applied. Only then may Milestone 2 be marked complete and feature routes be integrated behind its middleware.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
