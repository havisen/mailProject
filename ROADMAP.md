<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Lightweight Mail Panel — Project Roadmap

## Purpose and fixed scope

Build a lightweight standalone cPanel panel for one administrator, multiple standard IMAP/SMTP accounts, mail correspondence, basic contacts, and basic tasks. Use a fresh setup and leave the existing one-way sender unchanged. IMAP receives/synchronizes; SMTP sends. The external API is ONLY for sending supplied emails. Applications own authentication token generation and verification. There are no inbox, contacts, tasks, OTP, or status-reading API endpoints.

This roadmap governs the whole project and is implemented piece by piece. No milestone is implemented by this scaffold. Read docs/architecture.md for technical choices and docs/api.md for the narrowed API contract.

## Active work split

The next release slice is sender-only mail plus an independently developed contacts module. Read docs/workstreams.md for ownership, migration reservations, integration contracts and merge rules. Sender packages S0–S9 are detailed in docs/sender-plan.md; DeepSeek contacts packages C1–C4 are in docs/contacts-deepseek.md. S0–S2 and S4–S9 remain `not_started`; the platform-independent S3 core is present but blocked from acceptance by its unmet dependencies. Contact packages remain `not_started`.

SMTP feasibility does not wait for IMAP feasibility. SMTP accounts may be configured without IMAP. The sender slice defers manual attachments/drafts, replies, inbox and Sent-folder APPEND to the full-mail milestones below. Its partially_accepted state records mixed SMTP recipient results and never triggers a whole-message resend. Contacts do not implement task or received-message links yet. These slice-specific plans govern current execution while the milestones below remain the whole-project backlog.

The old empty combined contacts/tasks migration is retired as a placeholder. Contacts use migration 010; future tasks and received-message links use 020 and 030. The runner skips empty placeholder files. Nothing has been migrated.

## Deferred work

Teams, tenants, public signup, OAuth, Proton Bridge, webhooks, marketing/bulk sending, incoming APIs, contact/task APIs, remote Drafts sync, permanent deletion, advanced CRM, reminders, recurrence, and calendars are outside the first release.

## Working method and definition of done

Read AGENTS.md and handover.md; verify prerequisites; split the active milestone into small tickets with concrete acceptance criteria; implement only that milestone; run relevant checks; update the matching documentation, this roadmap, CHANGELOG.md, and handover.md. Hand over the exact next task and require the next contributor to update handover.md too.

Track each milestone as not_started, in_progress, blocked, or complete. A blocked milestone must state the missing condition and resolution path. Complete means implemented, verified, failure behavior checked, operational changes documented, and handover updated. Do not mark placeholders complete.

## Milestones

### Milestone 0 — Hosting and protocol feasibility

Status: not_started. Dependencies: None.

**Work:** Confirm PHP web/CLI and required extensions, Composer compatibility, database grants, private storage, cron, verified TLS, IMAP 993, and SMTP 465/587. Test folder discovery, UIDVALIDITY/UID, MIME/attachments, flags, safe move, and Sent APPEND through the selected adapter. Test SMTP independently.

**Acceptance:** A disposable mailbox passes the required protocol checks; record exact blockers without bypassing certificates.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 1 — Foundation and installation

Status: in_progress. Dependencies: 0. The isolated database-job runtime is implemented but not database-tested or integrated because hosting/foundation prerequisites remain missing.

**Work:** Set up routing, templates, services, validated configuration, private storage, structured logs, request IDs, explicit migrations, and CLI commands. Add database jobs with leases, interrupted-job recovery, bounded processing, and separate outbound/sync scheduling.

**Acceptance:** Fresh install and repeat migrations succeed; private files stay private; missing configuration fails clearly; interrupted jobs recover.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 2 — Independent administrator access

Status: not_started. Dependencies: 1.

**Work:** Create the administrator through CLI. Implement password hashing, login/logout/password change, session regeneration and expiry, Secure/HttpOnly/SameSite cookies, CSRF, throttling, safe audit events, and CLI recovery.

**Acceptance:** Anonymous access, wrong passwords, missing CSRF, expired sessions, password changes, and CLI recovery behave securely.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 3 — Mail account setup

Status: blocked. Dependencies: 2. The platform-independent SMTP account core is implemented, but milestones 0–2, HTTP/panel integration, migration execution, and real-provider verification are missing.

**Work:** Implement create/edit/disable/remove, separate SMTP and IMAP credentials, authenticated encryption, approved endpoints, sender identities, and Sent-folder mode. Validate network destinations, TLS, ports, and narrow operator-controlled private-host exceptions. Diagnose IMAP and SMTP separately; explicit test email only.

**Acceptance:** Valid account connects; wrong credentials and TLS failures are distinguishable; disabled accounts stop work; encrypted credentials restore correctly.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 4 — Durable outbound service

Status: not_started. Dependencies: 3.

**Work:** Create durable immutable submissions with To/Cc/Bcc, subject, text/HTML, attachments, Message-ID, expiry, limits, and account identity checks. Track queued/sending/accepted/failed/uncertain/expired/canceled states. Keep Sent-copy state separate; use bounded retries only when non-acceptance is established. Never auto-resend uncertain sends.

**Acceptance:** Double processing does not duplicate accepted mail; worker interruption is conservative; expiry is honored; failed Sent APPEND does not resend SMTP.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 5 — Send-only API

Status: not_started. Dependencies: 4.

**Work:** Implement only POST /api/v1/mail/send. Add hashed/revocable keys, mail:send sender scopes, rate limits, atomic idempotency, safe errors, OpenAPI and server-side examples. Callers supply and verify codes/links themselves. No incoming or external status-reading endpoints.

**Acceptance:** Valid backend submission is persisted before 202; identical concurrent retries create one submission; changed content conflicts; sender scopes and revocation work.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 6 — IMAP synchronization

Status: not_started. Dependencies: 3 and the worker from 1.

**Work:** Discover folders and roles; map ambiguous special folders explicitly. Sync newest Inbox metadata before bounded backfill. Persist UIDVALIDITY/UID checkpoints; reconcile remote changes and deletions; handle folder rename and UID reset; queue manual sync; show stale or capped state; serialize per-account work.

**Acceptance:** No duplicate occurrences; new mail during backfill is included; interrupted batches resume; one offline account does not block others; UID resets rebuild safely.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 7 — Safe message viewing

Status: not_started. Dependencies: 6.

**Work:** Decode headers, MIME and charsets. Show text and sanitized sandboxed HTML; block trackers, scripts, forms and remote resources. Load bodies through bounded work. Download authenticated attachments from private storage with filename, path and size checks. Handle malformed, oversized and missing messages.

**Acceptance:** No script execution or tracker requests; authenticated download enforced; malformed mail stays isolated; size limits are explicit; read flags change only as intended.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 8 — Compose, drafts and replies

Status: not_started. Dependencies: 4 and 7.

**Work:** Build plain-text compose with account selection, attachments, durable local drafts and save status. Sending creates an immutable submission snapshot. Add Reply-To-aware replies, reply-all self/duplicate removal, safe quoting, In-Reply-To/References, explicit attachment forwarding, and Outbox status. Drafts remain local in version 1.

**Acceptance:** Refresh preserves drafts; double-click sends once; correct reply headers and recipients; Bcc privacy; no silent attachment forwarding or sender switching; configured Sent mode prevents duplicate APPEND.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 9 — Mailbox actions and search

Status: not_started. Dependencies: 6–8.

**Work:** Queue read/unread, flags, archive, move, and move-to-trash with pending/failure states. Use UIDs and safe MOVE or targeted-delete fallback; never broad expunge. Defer permanent deletion. Add paginated synchronized-metadata search and References/In-Reply-To conversation grouping within one account.

**Acceptance:** Server failures remain visible; other-client changes reconcile; no unrelated deletion; search coverage is explicit; same subject alone does not join conversations.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 10 — Basic contacts

Status: not_started. Dependencies: 2; email integration needs 7.

**Work:** Create/edit/view/delete/search name, email addresses, phone, company text, and notes. Create or link from mail; warn about duplicate addresses without automatic merging. Show synchronized history and preserve message-link snapshots. No automatic contact harvesting.

**Acceptance:** Contacts work without mailboxes; creation from mail is reviewed; deletion does not delete email; links survive cache cleanup.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 11 — Basic tasks

Status: not_started. Dependencies: 2; links need 7 and 10.

**Work:** Implement title, notes, todo/in_progress/done, low/normal/high, optional date-only due date, completion/reopen, deletion and filters. Link optional contact/message; preserve fallback message context. Show open, overdue, today and completed. No reminders or recurrence.

**Acceptance:** Timezone-aware due states work; reopen clears completion time; deleted contacts/unavailable messages do not break tasks; validation retains entered content.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 12 — Operations and recovery

Status: not_started. Dependencies: Implemented feature milestones.

**Work:** Implement diagnostics, worker heartbeat, queue age/depth, account state, failed/uncertain sends, storage use, and safe retry controls. Pause repeated auth failures. Implement bounded retention, backup/restore, key rotation and cron monitoring. Restore with sending disabled and review backup-gap sends.

**Acceptance:** Separate restore succeeds; keys decrypt; no historical authentication mail automatically resends; stale cron and storage exhaustion are visible; cleanup never deletes remote mail or contacts/tasks.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

### Milestone 13 — Release verification

Status: not_started. Dependencies: 12.

**Work:** Run cPanel and second-provider integration, web/CLI checks, security and accessibility review, mobile checks, interruption/quota/delayed-cron tests, fresh install and upgrade. Back up before launch; deploy paused; smoke-test receiving/sending/API before enabling workers. Record rollback steps and release version.

**Acceptance:** Critical checks pass; documented restore is exercised; deployed behavior and documentation match; limitations and evidence are recorded.

**Handover:** Update handover.md with changes, configuration names, migration state, exact checks/results, blockers, and the next step. Require the next contributor to do the same.

## Release checkpoints

- After 3: secure access and independently tested accounts.
- After 5: separate transactional sending API.
- After 7: incoming messages and attachment reading.
- After 9: normal correspondence and organization.
- After 11: complete first-release feature set.
- After 13: verified production release.

## Assumptions and limits

Mail hosting already receives mail for the domain. Credentials, provider limits, SPF/DKIM/DMARC, and outbound network access must be verified. A one-minute cron schedule is eventual synchronization, not real time. SMTP acceptance is not proof of final delivery. The first release targets up to five accounts and moderate traffic. A move to persistent workers later should preserve submission and synchronization contracts.

Starting limits and security requirements are in docs/configuration.md and docs/security.md. Critical tests are in docs/testing.md. No production hosting or provider capability has been verified yet.
