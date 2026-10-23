# Task 09 — cleanup, backup, and sender recovery

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: outbound gates A–E accepted. Read `docs/authentication.md`, `docs/jobs.md`, `docs/submissions.md`, `docs/smtp-delivery.md`, `docs/api.md`, `docs/security.md`, `docs/database.md`, `docs/configuration.md`, and `docs/operations.md` plus their repositories/migrations.

Implement bounded, observable maintenance using existing jobs/CLI:

- Purge encrypted terminal submission content within 24 hours; retain required safe metadata/audit for 90 days, idempotency replay mappings for 7 days, logs for 30 days, and clean expired/revoked sessions and stale throttle/rate rows. Make limits configurable and batch-bounded. Never delete remote mail, contacts, tasks, active jobs, or replay data too early.
- Add diagnostics/dry-run behavior, safe exit codes, database-outage handling, and disposable-DB boundary tests.
- Provide real runnable backup/restore and credential/content key-rotation workflows without secrets in argv/repo/logs. Preserve old key versions until all ciphertext is re-encrypted and verified.
- Restore with API/outbound disabled. Expire overdue queued work; reconcile backup-gap/in-flight sends conservatively as uncertain. Never automatically resend historical/accepted/uncertain mail.
- Test interrupted cleanup, retention boundaries, missing keys, partial rotation, separate-instance restore, and no-resend recovery. If restore tools are unavailable, implement/test safe local logic and record the external blocker.

Do not perform a production backup, restore, deletion, or key rotation without explicit target authorization.
