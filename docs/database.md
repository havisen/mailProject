<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Database Introduction

Migration 002 now defines the proposed SMTP-only `mail_accounts` table, but it has not been applied or tested against MySQL/MariaDB. All other schema areas remain unimplemented. Use explicit versioned migrations, InnoDB, utf8mb4, prepared statements, and transactions for related local writes.

## Planned record groups

Administrator; mail accounts with encrypted credentials; folders and sync checkpoints; remote message occurrences and cached bodies; attachment metadata; local drafts; outgoing submissions; database jobs; contacts; tasks; contact/task/message links; API-key hashes and sender permissions; audit events.

Use stable public IDs. Unique remote occurrence identity is account, folder, UIDVALIDITY, UID. Keep Message-ID non-unique. Contact/task links retain minimal message snapshots when caches expire. Deleting contacts or tasks never deletes remote email.

## Job schema

Unapplied migration `007_create_jobs.php` creates `jobs`, `job_account_leases`, and `worker_runs`. Claim indexes cover queue/status/schedule/priority and expired leases; target and account indexes support recovery and diagnostics. `(type, dedupe_key)` prevents duplicate logical work when a caller supplies a key. Account lease rows serialize network operations without holding a database transaction open. MySQL/MariaDB `SKIP LOCKED`, JSON, InnoDB, and microsecond `DATETIME` support are prerequisites. There is no down migration; back up and inspect before manual rollback. See `docs/jobs.md` for state and recovery rules.

## Submission schema

The unapplied zero-byte `003_create_submissions_and_api_keys.php` placeholder was replaced with `003_create_submissions.php` before any migration execution. It creates `outgoing_submissions`, encrypted `submission_recipients`, and separate `submission_recipient_outcomes`. Unique public ID, Message-ID, and browser-operation indexes enforce stable identity/deduplication; state/account/deadline indexes support Outbox and maintenance. Account deletion is restricted. Sent-copy state is separate. API keys/idempotency remain a later additive migration. Migration 003 requires 002; job enqueue requires 007. See `docs/submissions.md` for recovery and retention.

## Delivery state

queued -> sending -> accepted, failed, or uncertain; queued may also become expired or canceled. Track Sent-copy status separately. SMTP acceptance uncertainty requires review or reconciliation, never automatic resend. Use leased jobs and atomic idempotency constraints. Document final schema, indexes, migration commands, and rollback implications as implemented.

## Active workstream split

See docs/workstreams.md for migration ownership. Migration 002 contains the SMTP account schema but has not been applied. The combined empty 005 placeholder stays unused; contacts are isolated in migration 010. The sender slice additionally uses `partially_accepted` for mixed recipient outcomes and records per-recipient status; no automatically repeated full submission may include an already accepted recipient.
