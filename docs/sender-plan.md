<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Sender-Only Mail — Detailed Implementation Plan

## Goal and fixed boundary

Deliver outgoing mail independently of the future inbox. One administrator configures SMTP sending accounts and API keys. A calling backend submits an email through the sole external endpoint, POST /api/v1/mail/send. The panel queues it durably, sends it over SMTP, and shows its state in an authenticated Outbox.

Read docs/workstreams.md for ownership and frozen platform contracts. DeepSeek owns contacts separately; sender work must not implement their contact feature. No mail receiving, IMAP connection, replies, forwarding, tasks, contact API, read/status API, OTP generation/verification, campaigns, tracking, OAuth, or modification of the existing sender is included.

For the first sender release, manual sending is a simple text/optional-HTML form without drafts or attachments. Those remain in later full-panel milestones. The future UI and API must share SubmissionService. No Sent-folder APPEND is implemented now; the provider may independently save a Sent copy.

## How to execute

Implement S0 through S9 in order, one reviewable package at a time. Every package starts with prerequisites, produces a working result, includes failure checks, and ends with documentation plus handover updates. Current status: S0/S1 foundation code is present but unverified; S2 authentication code is implemented but blocked on those dependencies and PHP/database/HTTPS evidence; the platform-independent cores of S3–S6 are present but remain blocked from integration and acceptance; S7–S9 are not started.

### S0 — Verify the sender's hosting prerequisites

**Deliver:** A documented sender preflight report and safe diagnostic command design.

- Confirm PHP 8.4 in web and CLI, compatible Composer, PDO MySQL, OpenSSL, Sodium, mbstring, fileinfo, and intl, plus extensions required by selected packages.
- Confirm database names/grants, HTTPS, private storage outside public/, available disk, and one-minute cron with an absolute CLI path.
- Confirm SMTP 465 implicit TLS or 587 mandatory STARTTLS and certificate verification against the actual provider.
- Test authentication independently of message submission. A real test email is an explicit action to a controlled recipient.
- Record provider sender-address restrictions, recipient/size/hourly limits, SPF/DKIM/DMARC checks, and whether the provider saves Sent copies.
- Do not block this sender release on IMAP support. The full roadmap's IMAP feasibility remains an outstanding receiving milestone.

**Accept:** A verified SMTP path or a specific blocker is documented. No secrets or private provider transcripts are copied to logs. Never bypass TLS checks.

### S1 — Build the minimal shared platform

**Deliver:** A bootable private application and the frozen contract used by DeepSeek.

- Implement Composer manifest and lockfile, autoloading, Slim, Twig, PDO, PHPMailer, configuration, safe error rendering, structured logs, request IDs, private storage and .gitignore.
- public/ is the only document root. Replace the starter index with the front controller; configure Apache routing and verify private file denial.
- Implement the migration runner contract in docs/workstreams.md; skip zero-byte placeholders; reject changed checksums for applied migrations. Do not execute the empty retired combined contacts/tasks migration.
- Implement CLI entrypoints for preflight, migrate, administrator management, worker execution and cleanup as they become available. Commands must fail nonzero on operational errors.
- Implement a shared disposable-database test harness and controlled SMTP transport test double.
- Publish Twig blocks, csrf_token variable, validation exception, controller/dependency registration, and contacts route loading contract. Keep empty unimplemented contact modules unloaded.

**Accept:** Fresh install and repeat migrations work; invalid config fails clearly; private files are inaccessible; basic web and CLI execution pass. Record the exact platform contract in handover.md so DeepSeek can integrate safely.

### S2 — Implement the independent administrator session

**Status:** blocked/implemented. Login/logout/password change, CLI recovery, versioned hash-only database sessions, Secure/HttpOnly/SameSite cookies, action-bound CSRF, database throttling, protected base routes, safe audit events, migrations, and tests are present. Composer/runtime/database/HTTPS verification has not run, so this package is not accepted.

**Deliver:** Login, logout, password change, CLI recovery, and protected navigation.

- Hash passwords, regenerate session IDs after login, set secure cookie attributes, use an independent cookie name, and implement 30-minute idle/12-hour absolute expiry.
- Require CSRF for browser mutations, including logout and account/key changes. Use generic login errors and database-backed throttling.
- Password change/reset invalidates existing sessions using an administrator security version checked by middleware.
- Establish protected pages for sending accounts, API keys, manual send, Outbox, and diagnostics. Add contacts navigation only when the module is integrated.

**Accept:** Anonymous requests fail; wrong credentials throttle; missing CSRF fails; sessions expire; password recovery works without email. DeepSeek may now verify authenticated contact routes when their module is ready.

### S3 — Configure SMTP-only sending accounts

**Status:** blocked/partial. Credential encryption, endpoint policy, migration, repository, service, connection-test contract, and module tests are present. The package cannot be accepted until S0–S2 provide verified hosting, Composer/bootstrap, migrations, dependency wiring, administrator authentication, CSRF, and the panel shell.

**Deliver:** Add/edit/disable accounts and independent connection tests.

- Fields: label, approved SMTP host, port, tls mode, username, encrypted password, From address/name, optional approved Reply-To, enabled state and configurable provider limits.
- Account setup must not require any IMAP settings. Use stable acct_ public IDs.
- Persist credentials with authenticated Sodium encryption and versioned key IDs. Keep the master key outside the database/document root. An unchanged password field retains its secret.
- Do not return saved credentials to HTML or JSON. Mask values in diagnostics.
- Restrict endpoints through operator-approved definitions, validate destinations and certificates, block metadata/loopback/private networks except an explicit narrow known-host exception.
- Keep connection/authentication test separate from the explicit test-email action. Test emails use the same durable submission path once S4/S5 is present.
- Disabled accounts reject new submissions and leave existing queued work visibly paused until re-enabled or expired. Never silently change an email's sender.

**Accept:** Real test account authenticates; wrong passwords, DNS/network problems, invalid certificates and sender rejection are distinguishable. Credential edit/disable paths and secret handling pass checks.

### S4 — Persist submissions, attempts and database jobs

**Deliver:** A durable enqueue service before real delivery is enabled.

- Store submission ID, account, source (API/panel/test), API-key reference if applicable, recipients, encrypted content reference, stable Message-ID, created time, deadline, state and safe error category.
- Store per-attempt and per-recipient SMTP outcomes. Do not retain raw body, code or reset link in audit/log records.
- Persist submission, encrypted content and job atomically where feasible. Use encrypted database content for the initial sender release to avoid database/filesystem commit gaps.
- Use DB job leases, bounded execution, a per-account sending lock, atomic claim, attempt history and recovery rules. Default one invocation per minute with about a 40-second budget; cap network operations to the remaining budget.
- Prioritize queued work by deadline then creation time. Do not start an attempt after send_before. It is an attempt deadline, not a guarantee against later provider delivery.
- Worker is disabled by default until configured and explicitly enabled.

State model:

queued -> sending -> accepted / partially_accepted / failed / uncertain
queued -> expired / canceled

A definite transient non-acceptance may move sending back to queued within the deadline. Terminal states never automatically requeue. Disabled-account work remains queued with a visible pause reason.

**Accept:** Transaction failure produces no accepted API response or orphan send. Concurrent workers cannot claim the same job. Deadline expiry and paused accounts are observable. Use deterministic transport doubles before enabling SMTP.

### S5 — Deliver over SMTP without unsafe retries

**Deliver:** A bounded worker and accurately reported provider outcomes.

- Use PHPMailer with TLS verification, explicit timeouts, configured account identity, UTF-8 content and a stable Message-ID. Never accept arbitrary SMTP credentials or From headers from an API request.
- Preserve Bcc only in the SMTP envelope; omit it from visible message headers. Do not infer recipient delivery from SMTP acceptance.
- Record success only after the provider's final acceptance. Separate operational exceptions from confirmed SMTP outcome.
- Retry definite temporary non-acceptance at 1, 2 and 4 minutes, maximum four total attempts, only while before the deadline. Permanent credential/configuration/recipient failures do not retry automatically; pause accounts after repeated authentication failures.
- A disconnect around final DATA acceptance, or an expired worker lease while sending without a recorded conclusive result, becomes uncertain. Never automatically resend it. Explain that exactly-once SMTP delivery cannot be guaranteed.
- Handle partial recipient acceptance explicitly: record accepted and rejected recipients; expose partially_accepted when appropriate. Never automatically retry the whole submission or recipients already accepted. For independent transactional emails, recommend one recipient per API request.
- Do not blindly retry a PHPMailer exception: some recipients may already have been accepted. Instrument the transport adapter to capture recipient and final DATA results; verify library behavior with a controlled SMTP server.
- If result persistence fails after SMTP acceptance, conservatively recover the attempt as uncertain; do not send again.
- No IMAP, Sent APPEND, or automatic contact creation occurs in this worker.

**Accept:** Exercise success, permanent rejection, temporary rejection, partial acceptance, timeout around DATA, interruption, and post-acceptance DB failure. Accepted/uncertain recipients never receive an automatic retry.

### S6 — Add API credentials and the sending endpoint

**Deliver:** Backend-only mail submission with documented errors and atomic idempotency.

- Issue a cryptographically random key, show it once, store only a hash and nonsecret lookup prefix. Support revoke, rotation, allowed account IDs, mail:send scope and last-used timestamp.
- Implement only POST /api/v1/mail/send. No GET submission endpoint, status_url, receiving API or OTP endpoints.
- Require Bearer authentication, application/json and an Idempotency-Key of 1–128 printable non-whitespace ASCII characters. Do not put keys in URLs or accept them through query parameters.
- Follow docs/api.md: required account_id, to, subject, text; optional html, cc, bcc, reply_to, send_before. Each recipient is an object with required email and optional name; to/cc/bcc are arrays; reply_to is one such object. Require nonempty to/text/subject, maximum 10 total recipients, 200 subject characters and 256 KiB JSON body. Version 1 has no attachments or custom headers.
- Support ordinary ASCII local parts and IDNA domain conversion; defer SMTPUTF8. Reject CR/LF header injection, duplicate recipients, invalid types/addresses and unknown fields. Limit recipient display names to 120 characters.
- send_before is an RFC3339 timestamp with timezone, normalized to UTC; default five minutes after server acceptance, no later than 24 hours away, and later than now. Callers should set it earlier than their token expiry.
- Scope idempotency to key + endpoint + supplied identifier, protected by a DB unique constraint and transaction. Use a server-keyed fingerprint of canonical validated caller input, avoiding offline guesses of low-entropy codes. Preserve whether send_before was omitted so changing defaults do not cause replay conflicts.
- Authenticate/authorize before idempotency replay. Identical requests return the original 202 response with its original ID, deadline and request ID. Different content returns 409. A replay neither creates a job nor consumes account recipient quota again.
- Retain idempotency records seven days. Never reuse identifiers for distinct emails. Revoking a key blocks new API access; it does not automatically cancel already accepted queued work. Admin cancellation handles queued work explicitly.
- Start limits at 30 requests/minute/key and 100 accepted recipients/hour/account, configurable within provider limits. Count attempts for request abuse protection and only new durable submissions for account quota; enforce counters atomically. Return Retry-After for 429.
- Persist first, then return 202. Return 503 when durable acceptance cannot be established; callers retry with the same key after ambiguous HTTP outcomes.

Example response:

{"data":{"id":"sub_example","status":"queued","send_before":"2026-10-22T18:05:00Z"},"request_id":"req_example"}

Use stable safe errors: 400 malformed JSON, 401 invalid key, 403 account/scope, 409 conflict, 413 size, 415 media type, 422 fields, 429 throttle, 503 availability. Every error has an error code/message and request ID. Disable CORS by default.

**Accept:** One real backend can submit; concurrent replays create one submission; changed payload conflicts; revoked/unauthorized keys cannot send; malformed/oversized input and DB failure fail safely. Publish OpenAPI and curl/PHP backend examples without real secrets.

### S7 — Add manual send, Outbox and operational visibility

**Deliver:** A small usable sender panel.

- Manual form: selected account, To/Cc/Bcc, subject, text and optional supplied HTML. No rich editor, drafts, replies or attachments in this slice.
- Form submission uses the same validation/queue service and a one-time operation identifier to prevent browser double-submit.
- Outbox lists timestamp, account, redacted recipient summary, source, state and safe failure category. Detail shows per-recipient outcomes and attempts, never authentication email body/code/reset link.
- Admin may cancel only an unclaimed queued submission. Cancellation races use an atomic condition; once sending starts, explain cancellation cannot be guaranteed.
- For failed/uncertain/partially accepted messages, never offer an automatic whole-message retry. A deliberate new submission requires review, only failed intended recipients, and renewed content; the originating app generates fresh authentication codes if needed.
- Diagnostics show heartbeat, oldest queued item, counts, account pauses and safe connection results. Warn after five minutes without worker heartbeat.
- Use clear terms: queued, SMTP accepted, partially accepted, failed, uncertain, expired, canceled. Never label SMTP accepted as delivered.

**Accept:** Admin can submit, inspect outcomes and safely cancel eligible work; double-click is idempotent; failures are actionable; no sensitive API body appears in the panel or logs.

### S8 — Add cleanup, backup and recovery

**Deliver:** Documented maintenance that does not resend stale authentication mail.

- API content is encrypted while queued. Purge it no later than 24 hours after terminal state. Keep audit/submission metadata 90 days, idempotency mappings seven days and diagnostic logs 30 days; remove plaintext recipient addresses from retained idempotency-only metadata where not needed.
- Use bounded cleanup batches. Keep the original accepted response available for idempotency after body deletion. Idempotent replay does not require retaining the original authentication code.
- Back up database, configuration and separately protected encryption/fingerprint keys. Document retention of sensitive content in backups; deleting live content does not remove older backups.
- Restore with sender workers disabled. Expire past-deadline submissions; review every restored in-flight/queued attempt that could already have sent after the backup point. Default unresolved cases to uncertain, never automatic replay.
- Document account credential rotation, key version rollover, API-key rotation, cron setup, upgrade, rollback and storage/database failure responses.

**Accept:** Restore onto a separate instance, decrypt necessary credentials, verify no automatic historical sends, exercise stopped cron, expired queue and disk/DB failure. Record recovery evidence and remaining limitations.

### S9 — Verify and release the sender slice

**Deliver:** A versioned sender-only release and combined platform/contact regression evidence where contacts are integrated.

- Verify fresh install, upgrade, PHP CLI/web agreement, HTTPS, private file denial, real SMTP on cPanel and a second standard provider, controlled receipt, SPF/DKIM/DMARC checks and provider limits.
- Run concurrency/idempotency, scope/revocation, CSRF/session, secret logging, uncertainty/partial recipients, delayed cron, expiry, cleanup and restore checks.
- Run DeepSeek contact checks after integration; no sender change may silently break their routes or schema.
- Launch with worker paused, smoke-test deliberately, enable worker and inspect queue/heartbeat. Record Composer lockfile, migrations and release version.

**Accept:** Evidence supports each claim; no critical known defect; setup/recovery instructions match the build. No authentication-email arrival SLA is claimed on one-minute shared-hosting cron.

## Operational and handover requirements

Maintain docs/sender-handover.md and root handover.md after every package, including exact commands/results, new configuration names, migrations, unverified provider behavior and the next package. Update docs/api.md/OpenAPI, operations, security, database and configuration docs when behavior is introduced. Keep implementation status separate from this planning status. Require the next contributor to read/update handover.md and pass the rule onward.
