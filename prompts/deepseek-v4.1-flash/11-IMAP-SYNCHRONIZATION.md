# Task 11 — IMAP synchronization

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: accepted foundation/auth/account/job runtime. Read `docs/architecture.md`, roadmap milestone 6, `docs/database.md`, `docs/security.md`, `docs/operations.md`, `docs/testing.md`, account/job contracts, and current IMAP/sync placeholders. No receiving API.

Implement standard verified-TLS IMAP with pure-PHP transport compatibility:

- Add encrypted per-account IMAP settings and independent connection diagnostics without weakening SMTP separation or logging credentials/transcripts.
- Add explicit schema for folders, roles, occurrences, UIDVALIDITY/UID checkpoints, sync state, stale/capped status, and required indexes. Use reserved migration 004 only if still unapplied/appropriate; otherwise add a new migration—never rewrite applied files.
- Discover folders/special-use roles; require explicit mapping when ambiguous. Persist account+folder+UIDVALIDITY+UID, never sequence numbers.
- Prioritize newest Inbox metadata, then bounded 90-day/5,000-item backfill. Include arrivals during backfill, resume checkpoints, rebuild safely on UIDVALIDITY reset, and serialize per account through existing jobs/leases.
- Reconcile flags/deletions/renames without broad deletion or blocking other accounts. Network calls honor remaining worker budget.
- Test interruption, concurrent workers, offline account isolation, duplicates, new arrivals during backfill, folder rename, and UID reset with synthetic fixtures. Record real-provider evidence separately.

Do not implement message rendering, compose, contacts, tasks, or mailbox mutations here.
