<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Never record secrets or private message contents.
Every handover must repeat this requirement for the next contributor.
-->

# Assignment 09 handover

Implemented the send-only credential boundary: Argon2id hash storage, nonsecret lookup prefixes, one-time reveal, `mail:send` plus explicit account scopes, Bearer-only PSR-15 middleware, revocation, linked replacement overlap, safe audit events, the protected-settings template, and migration 008's coordinated API access tables. No key material was written to documentation or fixtures.

Changed files are migration 008; `src/Api/{ApiKeyStore,ApiPrincipal,IssuedApiKey}.php`; `src/Repositories/ApiKeyRepository.php`; `src/Services/ApiKeyService.php`; `src/Middleware/ApiKeyMiddleware.php`; the API-key settings template; `tests/Unit/run-api-keys.php`; and related API/database/security/testing/roadmap/changelog documentation.

Run `composer install`, apply migrations 002, 003, then 008 through the future migration runner, and run `php tests/Unit/run-api-keys.php`. None ran here because PHP, Composer, and MySQL/MariaDB are unavailable. Parser validation is not runtime acceptance. Browser route/controller wiring, shared authentication/CSRF, migration execution, concurrent database behavior, and HTTP tests remain unverified. The named `00-SHARED-CONTRACT.md` is absent.

Integration requests: register `ApiKeyMiddleware` only on `/api/v1/mail/send`; add authenticated/CSRF-protected settings create/revoke/replace actions without overwriting shared routes; authorize the returned `ApiPrincipal` before Assignment 10 replay lookup; never cancel already accepted queued submissions merely because a key is revoked.

Exact next step: implement Assignment 10 against migration 008, then apply it to a disposable database and verify revocation-before-replay, atomic rate-limit races, idempotency concurrency, and panel CSRF.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
