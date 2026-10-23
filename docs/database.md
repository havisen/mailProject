<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Database Introduction

Migration 001 now defines administrator authentication tables and migration 002 defines the SMTP-only `mail_accounts` table, but neither has been applied or tested against MySQL/MariaDB. The CLI migration runner applies ordered nonempty PHP migration files and records filename, SHA-256 checksum, and UTC application time in `schema_migrations`. It serializes execution with a MySQL advisory lock, rejects modified or missing applied files, and records a migration only after its callable returns. Use explicit versioned migrations, InnoDB, utf8mb4, prepared statements, and transactions for related local writes.

## Administrator authentication schema

Unapplied migration `001_create_admin_and_jobs.php` creates `administrators`, `administrator_sessions`, and `administrator_login_throttles`. Despite its reserved filename, it creates no jobs; job ownership remains migration 007. A `singleton_key` unique/check constraint permits one administrator. Emails are normalized to lowercase before insert; password values are Argon2id hashes. `security_version` invalidates all sessions after password change/recovery.

Session rows store only SHA-256 token hashes plus a random CSRF secret, security version, HMAC IP/user-agent hashes, last seen, idle/absolute expiry, and revocation time. Throttle rows are keyed by HMAC scope hashes and contain only window/count/block timestamps. Short row-lock transactions update throttle state; no transaction spans password hashing or a network call.

Unapplied migration `006_create_audit_events.php` creates safe authentication audit metadata with indexes by time, administrator, and event type. It has no free-form payload column. Both migrations use InnoDB/utf8mb4 and have no automatic down migration. Once applied, never rewrite them; use an additive migration. Session/throttle/audit cleanup is not implemented, so retention enforcement remains an operations task.

Run `php bin/console.php migrate status`, then `php bin/console.php migrate up`, then status again. Commands require the documented environment and Composer dependencies. Reserved zero-byte files are skipped until implemented. MySQL/MariaDB DDL can auto-commit: a failed migration may leave partial schema even though the ledger correctly remains unmodified. Inspect/repair the disposable or restored schema before retrying; never edit an applied file or conceal a checksum difference. There are no automated down migrations.

## Planned record groups

Administrator; mail accounts with encrypted credentials; folders and sync checkpoints; remote message occurrences and cached bodies; attachment metadata; local drafts; outgoing submissions; database jobs; contacts; tasks; contact/task/message links; API-key hashes and sender permissions; audit events.

Use stable public IDs. Unique remote occurrence identity is account, folder, UIDVALIDITY, UID. Keep Message-ID non-unique. Contact/task links retain minimal message snapshots when caches expire. Deleting contacts or tasks never deletes remote email.

## Job schema

Unapplied migration `007_create_jobs.php` creates `jobs`, `job_account_leases`, and `worker_runs`. Claim indexes cover queue/status/schedule/priority and expired leases; target and account indexes support recovery and diagnostics. `(type, dedupe_key)` prevents duplicate logical work when a caller supplies a key. Account lease rows serialize network operations without holding a database transaction open. MySQL/MariaDB `SKIP LOCKED`, JSON, InnoDB, and microsecond `DATETIME` support are prerequisites. There is no down migration; back up and inspect before manual rollback. See `docs/jobs.md` for state and recovery rules.

## Submission schema

The unapplied zero-byte `003_create_submissions_and_api_keys.php` placeholder was replaced with `003_create_submissions.php` before any migration execution. It creates `outgoing_submissions`, encrypted `submission_recipients`, and separate `submission_recipient_outcomes`. Unique public ID, Message-ID, and browser-operation indexes enforce stable identity/deduplication; state/account/deadline indexes support Outbox and maintenance. Account deletion is restricted. Sent-copy state is separate. API keys/idempotency remain a later additive migration. Migration 003 requires 002; job enqueue requires 007. See `docs/submissions.md` for recovery and retention.

## Delivery state

queued -> sending -> accepted, failed, or uncertain; queued may also become expired or canceled. Track Sent-copy status separately. SMTP acceptance uncertainty requires review or reconciliation, never automatic resend. Use leased jobs and atomic idempotency constraints. Document final schema, indexes, migration commands, and rollback implications as implemented.

## API access schema

Unapplied migration `008_create_api_access.php` owns `api_keys`, `api_key_accounts`, `api_idempotency`, `api_rate_limit_buckets`, and `api_key_audit_events`. It depends on 002/003. Unique prefixes support hash lookup; account mappings constrain send scope; `(api_key_id, endpoint, identifier_hash)` serializes idempotency; bucket primary keys support atomic counters; audit rows contain only safe lifecycle event types. Hashes and HMAC outputs are binary/one-way values, not recoverable key or message content. Retain replay mappings for seven days after content cleanup. Migration execution and MySQL/MariaDB compatibility remain unverified.

The send service opens one transaction for recipient quota, encrypted submission/recipients, outbound job, and idempotency response. A uniqueness collision rolls back the losing submission/job/quota and reads the committed mapping; changed fingerprints conflict. Key request-abuse counters commit independently so replays and rejected authenticated calls count. The disposable acceptance script deliberately drops the idempotency table last to verify a database failure does not leave a sendable orphan. True simultaneous two-connection races remain required evidence.

## Active workstream split

See docs/workstreams.md for migration ownership. Migration 002 contains the SMTP account schema but has not been applied. The combined empty 005 placeholder stays unused; contacts are isolated in migration 010. The sender slice additionally uses `partially_accepted` for mixed recipient outcomes and records per-recipient status; no automatically repeated full submission may include an already accepted recipient.
