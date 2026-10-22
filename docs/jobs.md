<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Never record secrets or private message contents.
Every handover must repeat this requirement for the next contributor.
-->

# Database jobs and cron worker

## Purpose and current status

The job runtime provides durable, bounded work for outbound SMTP, IMAP synchronization, and maintenance without Redis or an always-on daemon. Migration `007_create_jobs.php`, `JobRepository`, the handler contracts, `WorkerRunner`, and `bin/worker.php` are implemented. Feature handlers are deliberately not part of this module. The foundation migration runner and dependency container are still missing, and the schema has not been applied or exercised on this machine.

## Model and workflow

`jobs` stores a stable `job_` ID, type, queue, target/reference, optional account reference, schedule, priority, attempts, lease, checkpoint, and a safe failure category. `JobRepository::enqueue()` participates in an existing caller transaction, allowing a domain record and job to commit together. A non-null `(type, dedupe_key)` is idempotent.

Claims use `SELECT ... FOR UPDATE SKIP LOCKED` in a short transaction. `job_account_leases` permits only one active operation for an account. The transaction commits before the handler runs, so network I/O never holds database locks. Handlers heartbeat and save bounded JSON checkpoints through `JobExecutionContext`; they must cap network calls with `networkTimeoutSeconds()`.

States are `queued -> leased -> completed|failed`, with explicit `leased -> queued` retry and `queued -> canceled`. Attempts increment on claim. `max_attempts` is a safety ceiling, not permission for generic retries. Only a handler may return `JobResult::retry()`.

Expired leases are reclaimed and passed to `JobHandler::recoverExpired()`. SMTP handlers must reconcile their submission and normally return `uncertain_outcome`; sync handlers may resume a durable checkpoint. Unknown handlers fail as `configuration`. The runner catches handler exceptions as `internal`, releases the account lease with the terminal transition, updates `worker_runs`, and uses nonzero exit status when work failed.

## Queues and scheduling

Use separate cron entries for `outbound`, `sync`, and `maintenance`. Outbound priority is independent from IMAP history, so backfill cannot starve transactional sending. The default run budget is 40 seconds, the default lease is 30 seconds, and no new job starts with less than five seconds remaining. A feature handler that can exceed a lease must checkpoint/heartbeat before expiry.

Example cPanel cron entries, with installation-specific absolute paths:

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/mail-panel/bin/worker.php --queue=outbound --budget=40
* * * * * /usr/local/bin/php /home/ACCOUNT/mail-panel/bin/worker.php --queue=sync --budget=40
* * * * * /usr/local/bin/php /home/ACCOUNT/mail-panel/bin/worker.php --queue=maintenance --budget=40
```

Do not copy the example unchanged: confirm the CLI PHP 8.4 and application paths with the host. Overlap is supported; row leases prevent duplicate claims and account leases serialize account work.

## Handler integration

`bootstrap/worker.php` may return an array mapping job type to an `App\Jobs\JobHandler`. The file is optional until feature handlers are installed. Each handler implements normal execution and expired-lease recovery. SMTP/IMAP business logic does not belong in the generic runtime.

Required environment names are `MAILPANEL_DB_DSN`, `MAILPANEL_DB_USER`, and `MAILPANEL_DB_PASSWORD`; values stay outside `public/` and version control. Run:

```sh
php bin/worker.php --help
php bin/worker.php --queue=outbound --budget=40
php tests/Unit/run-jobs.php
```

Exit codes are 0 for a clean run, 1 for a controlled worker/job failure, 2 for a database outage, 64 for invalid CLI usage, and 78 for missing database configuration. Output never contains database credentials, payloads, checkpoints, or provider error text.

## Recovery and operations

A stale `worker_runs.heartbeat_at` (operational target: five minutes) indicates cron or database trouble. Inspect the job type, target, status, attempt count, last heartbeat, and safe category. Do not manually change expired SMTP work to queued; install/use the sender recovery handler so the submission becomes uncertain. Sync work can be rescheduled only when its checkpoint semantics are known safe.

Back up all three job tables with the application database. After restore, keep outbound workers disabled and reconcile leased or sending submissions before enabling cron. Cleanup policies may prune completed job/worker history after the documented retention period, but must preserve active jobs and domain audit requirements.

## Verification and limitations

`tests/Unit/run-jobs.php` covers single handling, checkpoints, bounded timeout selection, explicit sync recovery, conservative SMTP recovery, and missing handlers. Disposable MySQL/MariaDB acceptance is still required for concurrent `SKIP LOCKED` claims, lease expiry, account serialization, and outage behavior. No real SMTP/IMAP transport is included or tested here.
