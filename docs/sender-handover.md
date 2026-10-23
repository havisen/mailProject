<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Sender Workstream Handover

## State

S0/S1 foundation code and the S2 administrator boundary are present but blocked from acceptance because dependencies are not installed/locked and no PHP/database/HTTPS/hosting evidence exists. S2 provides one-admin CLI creation/recovery, Argon2id passwords, versioned hash-only sessions, expiry, secure cookies, login/authenticated CSRF, database throttling, protected base routes, safe audit, migrations, and tests; see `docs/authentication.md` and `docs/handovers/02-administrator-access.md`.

Platform-independent S3–S6 cores are also present: SMTP accounts, durable jobs/submissions, conservative SMTP delivery, API keys, and the sole send endpoint. They remain isolated and unaccepted. No receiving, contacts, tasks, OTP generation/verification, or external status API was added. The old sender is unchanged.

## Next step

Install PHP 8.4/Composer and required extensions, create/review `composer.lock`, provision a disposable MySQL/MariaDB database, and run foundation/migration/authentication checks plus real HTTPS cookie/CSRF checks. Record exact output and correct failures before accepting S1/S2. Then wire S3–S6 only through the shared middleware/container/worker/API contracts and run their documented database/concurrency/controlled-SMTP checks. No real hosting, database, PHP runtime, HTTPS, or SMTP test has run.

## Required updates

After each package record changed files, commands/results, configuration names, migrations, uncertainty/retry behavior and exact next step here and in root handover.md.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
