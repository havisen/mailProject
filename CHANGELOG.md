<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Changelog

## Unreleased

- Added a token-efficient DeepSeek single-task wrapper with a fixed review packet, git/test evidence, secret-redaction rules, and a mandatory stop before the next package.
- Added Milestone 2 administrator access: single-admin CLI provisioning/recovery, Argon2id passwords, hash-only versioned database sessions, idle/absolute expiry, Secure/HttpOnly/SameSite cookies, login and authenticated CSRF, database email/IP throttling, password change/logout, protected Slim routes, safe audits/security headers, migrations, tests, and documentation. Runtime/database/HTTPS verification remains blocked on this host.
- Added the shared foundation runtime: strict environment validation, MySQL PDO setup, private redacted JSON logs, random request IDs, fail-closed Slim bootstrap, checksum/lock migration runner, CLI migration and preflight commands, and focused configuration/migration tests. Dependencies are declared but not installed or locked; runtime/database verification remains blocked on this host.
- Added Assignment 06 database jobs: atomic leased claims, per-account serialization, handler-owned expired recovery, resumable checkpoints, worker heartbeats, independent queues, a bounded CLI, tests, and operations documentation. Migration 007 is not applied or database-verified.
- Added Assignment 07 outgoing persistence: encrypted immutable snapshots and recipients, stable IDs/Message-IDs, transactional enqueue, browser operation deduplication, safe cancellation/claim transitions, recipient outcomes, conservative retry/uncertain recovery, tests, and documentation. Migration 003 is not applied or database-verified.
- Added Assignment 08 SMTP delivery: PHPMailer 7 verified-TLS adapter, separate RCPT/DATA instrumentation, safe partial/uncertain outcomes, bounded retries, account authentication-failure pausing, controlled worker doubles, and operations documentation. Runtime/transport verification remains blocked.
- Added Assignment 09 API keys: one-time high-entropy issuance, Argon2id hash-only persistence, `mail:send` account scopes, Bearer middleware, revocation/replacement overlap, safe audit events, settings template, coordinated migration 008, tests, and documentation. HTTP/database verification remains blocked.
- Added Assignment 10 send-only API: strict JSON/recipient/IDNA validation, HMAC idempotency, request and recipient limits, atomic submission/job/replay persistence, safe HTTP errors, OpenAPI/examples, and focused tests. Shared route/bootstrap and runtime/database verification remain blocked.

## SMTP account core — partial sender package S3

Integrated the usable result from the supplied DeepSeek transcript into the project. Added versioned Sodium credential encryption, account/field context binding, SMTP endpoint allow-list and special-address blocking, migration 002, prepared account persistence, account lifecycle validation, and a transport-independent SMTP connection tester. Added a 40-check dependency-free test runner plus account, security, configuration, operations, roadmap, and handover documentation.

The transcript's corrupted syntax was reconstructed and its invalid secretbox AAD construction was corrected with a keyed context-derived key. Runtime verification remains unavailable because PHP and Composer are not installed on this machine. HTTP/auth/CSRF, migration-runner, PHPMailer, database, and provider integration remain incomplete.

## Workstream planning update

Added a detailed sender-only plan (S0–S9), a DeepSeek contacts assignment (C1–C4), shared platform/file ownership contracts, migration reservations, and separate workstream handover notes. Clarified that SMTP delivery does not depend on IMAP, the API has only a send endpoint, and contacts integrate through the shared platform. No application code was implemented.

## Starter scaffold

Created project documentation, empty implementation placeholders, and a minimal public index. No application features, dependencies, database migrations, or server protections are implemented. API scope is strictly outgoing email submission.

Record implementation changes by milestone here; keep current operational state and exact next steps in handover.md.
