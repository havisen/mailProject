<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Operations Introduction

This scaffold is not deployable as a working mail service. Complete hosting feasibility and installation before exposing it publicly.

## Hosting preflight

Confirm web and CLI PHP 8.4, compatible Composer dependencies, PDO MySQL, Sodium, OpenSSL, mbstring, DOM/XML, fileinfo, and intl. Confirm private writable storage, cPanel database names and grants, one-minute cron, certificate trust, and outbound IMAP 993 and SMTP 465/587. Test SMTP separately from IMAP.

## Deployment requirements

Dedicated subdomain, database credentials, session cookie, API keys, and encryption key. Set the document root to public/ only. Keep all private storage and configuration outside it. Use HTTPS, production-safe errors, explicit migrations, absolute cron paths, and pinned dependencies. Do not reuse the old panel's secret values.

## Worker and recovery requirements

Use database jobs with leases, bounded execution around 40 seconds, per-account locks, heartbeat monitoring, and resumable batches. Prioritize outbound delivery independently of IMAP backfill. Alert visibly if the worker heartbeat is older than five minutes. Record actual cron commands only after implemented.

Back up database, private files, configuration, and encryption key securely. Restore with outbound sending disabled and accounts paused. Review in-flight and backup-gap sends before resuming; never blindly resend historical authentication emails. Test restoration on a separate installation.

## Starting retention

Logs: 30 days. Audit/submission metadata: 90 days. Temporary uploads: 24 hours. API content: encrypted while queued and purged within 24 hours after terminal state. Document provider-side retention separately. Do not auto-delete remote email during cleanup.

Add exact installation, upgrade, rotation, cleanup, backup, restore, and rollback commands as their features are implemented.

## Implemented worker command

After applying migration 007, run the bounded CLI with an absolute PHP 8.4 path. Separate one-minute cron entries should invoke `php bin/worker.php --queue=outbound --budget=40`, `--queue=sync`, and `--queue=maintenance`; use absolute installation paths as shown in `docs/jobs.md`. Overlap is supported by row/account leases. Exit 2 means the database was unavailable and no work was intentionally processed. Keep outbound disabled after restore until leased sends are reconciled. The migration runner and feature handler registrations remain integration prerequisites.

## SMTP account operational notes

- Back up credential master keys separately from, but together with, the database recovery set. Losing all historical keys makes stored SMTP passwords unrecoverable.
- To rotate keys, add a new key ID and set it active. Keep old keys available until every stored value using them is re-encrypted or removed.
- Disabling an account must stop new submissions and leave queued work visibly paused until later queue rules resolve it.
- A connection test may connect and authenticate but must not send message DATA. Never disable certificate verification to make a test pass.
- Migration 002 and all provider behavior are currently unverified. Do not deploy this scaffold.
