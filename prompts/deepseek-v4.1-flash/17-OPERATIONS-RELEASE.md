# Task 17 — complete operations and release verification

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: all intended release milestones accepted. Read roadmap milestones 12–13 plus operations/security/testing/configuration/database docs. Implement only the first incomplete package per run:

- **O1 Diagnostics:** authenticated queue depth/age, worker heartbeat/staleness, account/sync state, failed/partial/uncertain sends, storage usage, safe connection results, cron visibility, and non-destructive health CLI. Never expose secrets/content.
- **O2 Recovery:** bounded retention/cleanup integration, key/API credential rotation, backup/restore/rollback commands and separate-instance exercise. Restore with sending disabled; reconcile backup-gap work conservatively; no historical auto-resend or remote-mail deletion.
- **O3 Quality:** full fresh-install/upgrade/regression/concurrency/interruption/quota/delayed-cron/database/storage/TLS/provider tests; secret/log review; WCAG keyboard/labels/contrast/focus checks; mobile layouts; documentation/OpenAPI parity.
- **O4 Release:** version and release notes, exact cPanel install/cron/document-root steps, paused launch plan, rollback, deliberate smoke tests, monitoring, and post-launch checklist.

Do not deploy, change DNS, send provider mail, restore production, rotate production keys, or enable workers/API without explicit user authorization naming the target. Without it, stop at a verified release candidate/runbook and report the required operator action. Never claim provider, restore, accessibility, or production evidence that was not actually obtained.
