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

- Added Assignment 06 database jobs: atomic leased claims, per-account serialization, handler-owned expired recovery, resumable checkpoints, worker heartbeats, independent queues, a bounded CLI, tests, and operations documentation. Migration 007 is not applied or database-verified.

## SMTP account core — partial sender package S3

Integrated the usable result from the supplied DeepSeek transcript into the project. Added versioned Sodium credential encryption, account/field context binding, SMTP endpoint allow-list and special-address blocking, migration 002, prepared account persistence, account lifecycle validation, and a transport-independent SMTP connection tester. Added a 40-check dependency-free test runner plus account, security, configuration, operations, roadmap, and handover documentation.

The transcript's corrupted syntax was reconstructed and its invalid secretbox AAD construction was corrected with a keyed context-derived key. Runtime verification remains unavailable because PHP and Composer are not installed on this machine. HTTP/auth/CSRF, migration-runner, PHPMailer, database, and provider integration remain incomplete.

## Workstream planning update

Added a detailed sender-only plan (S0–S9), a DeepSeek contacts assignment (C1–C4), shared platform/file ownership contracts, migration reservations, and separate workstream handover notes. Clarified that SMTP delivery does not depend on IMAP, the API has only a send endpoint, and contacts integrate through the shared platform. No application code was implemented.

## Starter scaffold

Created project documentation, empty implementation placeholders, and a minimal public index. No application features, dependencies, database migrations, or server protections are implemented. API scope is strictly outgoing email submission.

Record implementation changes by milestone here; keep current operational state and exact next steps in handover.md.
