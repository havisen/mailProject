<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Architecture Introduction

## Target

A single PHP application for a cPanel-hosted private mail panel, with one administrator and multiple mailboxes. Use Slim 4, Twig, PDO, PHPMailer, Webklex PHP IMAP with pure-PHP transport, HTML Purifier, and Sodium. Confirm package compatibility and required provider capabilities in Milestone 0; dependencies are not installed in this starter.

Target PHP 8.4, MySQL 8.0+ or MariaDB 10.6+, InnoDB, and utf8mb4. The interface is server-rendered HTML/CSS with small vanilla JavaScript enhancements. No Redis, persistent process, or frontend build service is required.

## Components and flow

Browser requests now enter the fail-closed Slim bootstrap at `public/index.php`, receive an internal random request ID, and will later pass through authentication/middleware, controllers, services, and repositories. No feature routes are registered yet. Twig is configured with HTML autoescaping and strict variables; compiled-template caching remains disabled until private cache operations are verified. Configuration, private JSON logs, PDO setup, and the CLI-only checksum migration runner live outside `public/`. The send-only external API will call the same submission service as the panel. Cron workers process durable outbound submissions and IMAP synchronization separately so historical backfill cannot block transactional sending.

The mail server owns received messages, folders, and remote flags. The database owns contacts, tasks, local drafts, submissions, job state, and link snapshots. Message caches are rebuildable. SMTP acceptance does not prove final recipient delivery.

The implemented submission boundary commits an encrypted immutable snapshot and outbound job together. The implemented worker boundary claims jobs and per-account leases in short transactions, then releases database locks before feature handlers perform network work. SMTP transport and API/controller wiring remain separate modules.

The SMTP module now instruments RCPT and final DATA separately. A library exception is not itself proof of non-delivery. Conclusive DATA acceptance, mixed RCPT outcomes, and ambiguity are persisted as accepted, partially accepted, or uncertain; only definite pre-acceptance temporary failures can return to queued.

The implemented API path is `ApiKeyMiddleware -> SendMailController -> SendRequestValidator -> ApiSendService -> SubmissionService`. Request throttling commits independently; account quota, encrypted content, outbound job, and HMAC idempotency mapping share one database transaction. No external read/status component exists.

## Critical invariants

- Persistent remote identity: account + folder + UIDVALIDITY + UID.
- Never persist IMAP sequence numbers as durable identifiers.
- Message-ID supports threading and reconciliation but is not a globally unique database key.
- Keep SMTP acceptance separate from Sent-copy state.
- Never automatically resend an uncertain SMTP submission.
- Preserve contact/task context when a message leaves the cache.
- Explicit migrations only; no schema creation in ordinary page requests.
- Only public/ is web-accessible; configuration, dependencies, and private files stay outside it.

Expand this document as each module is implemented and record decisions in handover.md.
