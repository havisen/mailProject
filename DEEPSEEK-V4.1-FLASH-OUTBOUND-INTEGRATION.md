# DeepSeek v4.1 Flash — outbound integration

Work directly in this repository. Keep responses short: do not restate this prompt, narrate routine steps, or paste whole files. Edit files, run available checks, then return only the requested handover. Do not commit or push.

## Start

1. Read `AGENTS.md`, `handover.md`, `ROADMAP.md`, `docs/workstreams.md`, and `docs/sender-plan.md`.
2. Find the first incomplete gate below from source/handover. Read only its relevant module docs/code, migration, bootstrap/routes, and tests; do not load unrelated future modules.
3. Inspect `git status`; preserve unrelated/user changes. Treat existing cores as code to integrate and repair, not rewrite. Verify interfaces from source rather than guessing.
4. Check prerequisites first. Never use production data or apply destructive tests outside an explicitly disposable database. If PHP/Composer/MySQL/SMTP fixtures are unavailable, continue safe work for this gate and record the exact blocker—never claim runtime/provider verification.

## Scope and order

On each run, implement only the first incomplete gate, test/document it, then stop. Reuse this same prompt for the next gate. If the current gate has a real dependency blocker, document it and stop rather than bypassing security.

### A — Authenticated SMTP-account panel

- Wire the existing account repository/service/cipher/policy into dependency bootstrap.
- Implement server-rendered list/create/edit/disable/remove and connection-test controller routes/templates.
- Protect every route with administrator authentication; require action-specific CSRF for every mutation/test.
- Never render/log stored credentials. Preserve an unchanged password. Keep endpoint allow-listing, verified TLS, safe error categories, and explicit SMTP test behavior. Do not add IMAP or send mail during a connection test.

### B — Durable submissions and jobs

- Wire migrations 003/007 and existing submission/job services without creating a competing queue.
- Ensure submission content, recipients, outbound job, and browser operation ID commit atomically; rollback leaves no sendable orphan.
- Implement atomic queued cancellation, safe claim transitions, deadlines, encrypted snapshots, Bcc privacy, and one-active-operation-per-account behavior.
- Preserve uncertain outcomes and handler-owned expired-lease recovery. Never blindly requeue SMTP.

### C — SMTP worker runtime

- Register the existing `smtp.send` handler in the real worker bootstrap and keep outbound separate from sync/history work.
- Support one-minute cron, absolute PHP paths in docs, about a 40-second budget, remaining-time network bounds, overlap-safe leases, heartbeat, and visible exit codes.
- Preserve 1/2/4-minute definite-transient retries, four attempts maximum, deadline checks, partial-recipient outcomes, account auth pauses, and no resend of accepted/uncertain recipients.
- Use controlled SMTP fixtures for accepted, rejected, transient, partial, final-DATA ambiguity, interrupted lease, and persistence failure after acceptance. Do not weaken TLS.

### D — Sole external API

- Register exactly `POST /api/v1/mail/send` behind request IDs and Bearer `ApiKeyMiddleware`; CORS remains disabled. Admin sessions never authenticate the API.
- Wire existing validation, account scope, revocation, rate limits, HMAC idempotency, quota, submission/job transaction, and safe error mapping.
- No GET status/status URL, inbox, contacts, tasks, attachments, OTP/code generation, or verification endpoints.
- Verify identical replay returns the original 202 without new quota/job, changed payload returns 409, revoked keys cannot replay, and no 202 occurs before commit. Validate behavior against `docs/openapi.yaml`.

### E — Manual Send, Outbox, diagnostics

- Add authenticated server-rendered manual Send using the same `SubmissionService` and a browser operation ID. No attachments/drafts/replies in this slice.
- Add Outbox list/detail with account, source, timestamps, state, safe failure category, attempts, and per-recipient outcomes; never display/log message bodies, Bcc, credentials, auth codes, raw SMTP transcripts, or API secrets.
- Allow only atomic cancellation of still-queued work. Do not offer broad retry for failed/partial/uncertain mail.
- Add safe worker diagnostics: heartbeat, queue counts/age, account pauses, and stale-worker warning.

## Non-negotiable boundaries

- Build only this outbound slice; leave the old sender unchanged.
- Only `public/` is web-accessible. Use prepared statements, existing encryption/configuration, Twig autoescape, shared auth/CSRF, and verified TLS.
- Do not invent parallel services, authentication, schema, queues, routes, or fake commands.
- Do not rewrite an applied migration. Check migration status first; use an additive migration only for a demonstrated schema defect.
- SMTP acceptance is not delivery. Accepted, partially accepted, failed, uncertain, expired, and canceled remain distinct. Terminal/uncertain work never auto-requeues.
- Do not store secrets, real messages, credentials, codes, tokens, private fixtures, or production logs.

## Verification and documentation

Add meaningful tests for integration risks, not file-existence tests. Run all available foundation/auth/account/job/submission/SMTP/API tests plus new route/UI checks and neighboring regressions. Database tests must be opt-in and disposable; concurrency needs separate connections/processes. Distinguish doubles, controlled SMTP evidence, and real-provider evidence.

Update behavior-relevant docs, `ROADMAP.md`, `CHANGELOG.md`, module handovers, and root `handover.md`. Record migrations as applied or unapplied, exact commands/results, configuration names, security/retention/recovery implications, blockers, and the exact next action.

## Final response — only this

1. Current gate and result: complete, partial, or blocked.
2. Changed files.
3. Migrations/configuration and whether applied.
4. Exact test commands and results.
5. Unverified behavior/known issues.
6. Exact next step, including the next gate when ready.

End exactly with: **Read and update handover.md before handing over, and require the next contributor to do the same.**
