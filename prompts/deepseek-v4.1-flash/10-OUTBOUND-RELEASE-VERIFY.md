# Task 10 — verify outbound-only release

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: tasks 01, outbound A–E, and task 09 accepted. This is verification/repair, not new feature development. Read `docs/sender-plan.md`, sender module handovers, `docs/openapi.yaml`, `docs/testing.md`, `docs/security.md`, and `docs/operations.md`.

Create a reproducible outbound release evidence run:

- Fresh install and upgrade from the prior schema; repeat migrations; reviewed lockfile; web/CLI parity; private-file denial.
- End-to-end administrator, account CRUD/test, manual Send/Outbox/cancel, API key/scope/revocation, sole send API, worker/cron/heartbeat, cleanup, backup, and restore.
- Separate-process concurrency for job claims, account locks, idempotency, quota/rate boundaries, and cancellation races.
- Controlled SMTP acceptance/rejection/transient/partial/final-DATA ambiguity/crash/post-acceptance DB failure; delayed cron, expiry, storage/database outage, TLS failure, and no unsafe resend.
- Validate actual HTTP behavior against `docs/openapi.yaml`; run security/accessibility smoke checks and secret/log scans.
- Real cPanel and second-provider tests only with operator-supplied authorized accounts/recipients. Never disable TLS or send an unapproved test message. Clearly separate controlled and provider evidence.

Fix only defects inside the outbound slice and add regressions. Produce version/rollback/release notes, but do not deploy or enable production workers/API.
