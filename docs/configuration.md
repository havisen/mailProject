<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Configuration Introduction

Configuration files are empty placeholders. Introduce validated settings during implementation, document their exact names here, and never include real values in documentation.

## Required categories

- Application URL, environment, timezone, private storage paths, and session settings.
- Database host, port, database name, username, and secret.
- Credential-encryption key and key version, stored separately from the database.
- Approved mail provider endpoints and explicit narrow exceptions for legitimate private cPanel endpoints.
- Per-account SMTP/IMAP settings, sender identities, Sent-copy mode, and limits.
- Worker budgets, synchronization windows, upload limits, retention, and API limits.

## Initial defaults

One-minute cron; one IMAP connection per account; initial target up to five accounts; newest 200 Inbox messages first; backfill up to 90 days with a visible 5,000-record cap per account. Other folders synchronize when enabled. List pages contain 50 messages, maximum 100. Network connection timeout 10 seconds, operation timeout 20 seconds.

Attachment limit 10 MiB per file, outgoing MIME limit 20 MiB or lower provider limit, decoded rendered body limit 2 MiB. Account for MIME encoding overhead. Session idle timeout 30 minutes, absolute timeout 12 hours. Store timestamps in UTC; display with administrator timezone. Limits must be visible rather than silently truncating or skipping content.

## SMTP account configuration names

Names only; real values must not be committed.

- `security.credential_keys`: map of key ID to base64-encoded 32-byte master key.
- `security.active_key_id`: key used for new credential encryption; it must exist in `credential_keys`.
- `mail.approved_endpoints`: operator-owned list of `host`, optional pinned `port`/`tls`, and an exceptional `allow_private` flag.
- `mail.connect_timeout_seconds`: default 10 seconds.
- `mail.operation_timeout_seconds`: default 20 seconds.

Per-account limits are stored in `mail_accounts`: `max_recipients`, `max_hourly_recipients`, and `max_outbound_bytes`. Configuration loading and validation remain part of sender package S1.
