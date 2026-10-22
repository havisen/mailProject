# Assignment 06 — Background jobs handover

Implemented migration `007_create_jobs.php`, database-backed atomic claims, job and account leases, checkpoints, safe failure categories, worker heartbeat records, handler registration, expired-lease delegation, a bounded runner, and the real `bin/worker.php` CLI. Added queue configuration, unit-risk checks, and operational/database/configuration documentation.

The existing repository has no `00-SHARED-CONTRACT.md`; `AGENTS.md`, `handover.md`, `ROADMAP.md`, and `docs/workstreams.md` were used. The current workstream contract reserves older sender migrations but does not prohibit additive 007; no migration has been applied.

Run `php tests/Unit/run-jobs.php` and then use a disposable MySQL 8.0+/MariaDB 10.6+ database to apply 007 and exercise two concurrent workers. This machine has no PHP, Composer, or database client, so those checks are not run here. Feature owners must register their handlers in `bootstrap/worker.php`: SMTP expired recovery must mark the submission uncertain, while sync may resume a safe checkpoint. No SMTP/IMAP logic was added.

Exact next step: complete Assignment 07's transactional submission/job enqueue, register its conservative outbound lease-recovery handler when the transport integration exists, then run the unit and disposable-database checks before enabling cron.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
