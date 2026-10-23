<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it before handing work over.
Never record secrets or private message contents. Repeat this requirement for the next contributor.
-->

# SMTP delivery worker

## Purpose and status

The SMTP delivery module turns an already durable `smtp.send` job into one bounded SMTP attempt without unsafe duplicate retries. It provides `SmtpTransport`, PHPMailer 7 integration, command instrumentation, `SendWorker`, and controlled transport test doubles. It does not implement the API, UI, IMAP, Sent-folder APPEND, contact creation, or attachment storage.

The code is implemented but blocked from acceptance by the missing foundation/bootstrap, unapplied migrations, unavailable PHP/Composer/MySQL runtime, absent controlled TLS SMTP server, and real-provider checks. PHPMailer `^7.1` and PSR interfaces are declared in `composer.json`; no lock file was generated because Composer is unavailable.

## Delivery workflow

`SendWorker` checks the account before claiming content. Missing, disabled, or paused accounts are not contacted. `SubmissionService::claimForSending()` checks the attempt-start deadline, decrypts the immutable snapshot, and atomically changes `queued -> sending`. The worker uses the stable stored Message-ID, configured From/Reply-To, To/Cc headers, and Bcc envelope recipients. Bcc is passed with PHPMailer's SMTP Bcc mechanism and is not exposed by application responses.

`InstrumentedSmtp` captures only numeric/safe command facts: every RCPT acceptance and the final DATA state. It never retains transcripts or provider text. A successful DATA reply makes accepted RCPT recipients final. Mixed RCPT acceptance becomes terminal `partially_accepted`; the whole submission is never retried. A conclusive 4xx before acceptance may retry; 5xx/configuration/authentication/TLS failures are terminal. Disconnect, timeout, or exception while final DATA may have succeeded becomes `uncertain` and never retries.

If SMTP returns acceptance but database persistence fails, `SendWorker` abandons the job lease instead of acknowledging it. Expired-lease recovery later atomically changes the still-`sending` submission to `uncertain`. This is conservative: SMTP cannot provide exactly-once delivery.

## Retries and account pausing

Retry delays after definite temporary non-acceptance are one, two, and four minutes, with four total attempts and deadline checks before scheduling. Every attempt records per-recipient outcomes. Accepted or unknown recipients are never automatically retried. Authentication failures are safe-category failures; when `AccountService` is integrated with `JobRepository`, the third authentication failure in 15 minutes pauses the account. Pausing does not cancel previously accepted/terminal mail.

For authentication email, callers should send one recipient per API request. Mixed recipients can produce partial acceptance that cannot be safely repeated as a whole.

## TLS, limits, and privacy

`PhpMailerSmtpTransport` requires SMTP authentication and either implicit TLS/465 or STARTTLS/587 from the validated account. Peer and hostname verification are enabled and self-signed certificates are rejected. Debug output is discarded. Connection/operation timeouts are clamped to the worker's remaining budget. Account recipient/size limits are enforced before enqueue by the submission module; provider limits still apply.

Never log the transport message, credentials, bodies, Authorization headers, SMTP transcript, or raw exceptions. Safe visible categories are used instead. Backups and restores follow `docs/submissions.md`; restore with outbound disabled and reconcile `sending`, `uncertain`, and leased work before cron resumes.

## Installation and integration

Requirements: PHP 8.4 CLI, PDO MySQL, Sodium, OpenSSL, JSON, Composer, PHPMailer 7.1, applied migrations 002/003/007, verified CA trust, and provider connectivity. Install dependencies with:

```sh
composer install --no-dev --classmap-authoritative
```

The integration owner must build `AccountService` with `JobRepository`, build `SubmissionService`, instantiate `PhpMailerSmtpTransport`, register `SendWorker` as `smtp.send` in `bootstrap/worker.php`, and pass values from `config/mail.php`. Do not register a generic retry handler.

Run checks:

```sh
php tests/Unit/run-unit.php
php tests/Unit/run-jobs.php
php tests/Unit/run-submissions.php
php tests/Unit/run-smtp-delivery.php
php tests/Integration/run-jobs-and-submissions.php
```

`run-smtp-delivery.php` is a controlled transport double covering acceptance, partial recipients, transient retry, attempt ceiling, uncertainty, disabled accounts, expired leases, and database persistence failure. It does not prove PHPMailer/provider behavior. A controlled verified-TLS SMTP server must still exercise RCPT 250/450/550 combinations, DATA 250/450/550, final-DATA disconnect, and transport timeouts before acceptance.

## Troubleshooting and exact next step

- `account_disabled`: review account pause reason and credentials; explicit operator action is required.
- `authentication`: verify credentials without logging them; three recent failures pause the account when fully integrated.
- `tls`: fix endpoint/certificate trust; never bypass verification.
- `uncertain_outcome`: reconcile with provider logs or recipient evidence; never click/requeue broadly.
- stale leased send: allow registered expired recovery to mark it uncertain.

Exact next step: install PHP/Composer and a disposable database, generate a Composer lock, apply migrations, register the worker, run all local checks, then execute controlled TLS SMTP cases before any real-provider send.

**Read and update handover.md before handing over, and require the next contributor to do the same.**
