<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Lightweight Mail Panel

In-progress private cPanel-hosted mail panel. It contains an unverified foundation and administrator authentication boundary plus isolated outgoing-mail modules, alongside empty future-feature placeholders. It is not an integrated or runtime-verified mail application and must not be deployed as a secured service yet.

## Agreed purpose

- One administrator managing multiple standard IMAP/SMTP mailboxes.
- Receive, read, compose, reply, and forward mail inside the panel.
- A sending-only API for application-generated authentication and transactional emails.
- Basic contacts and tasks inside the panel, with links to messages.
- A fresh installation separate from the existing one-way sender.

IMAP receives and synchronizes messages; SMTP sends them. Calling applications generate and verify their own authentication codes and reset links. The panel only sends the supplied email.

## Start here

1. Read AGENTS.md and handover.md.
2. Read ROADMAP.md and docs/architecture.md.
3. Read docs/workstreams.md for the sender/contacts split and shared integration contract.
4. Sender contributor: use docs/sender-plan.md. DeepSeek contacts contributor: use docs/contacts-deepseek.md.
5. Begin with the relevant feasibility checks; implement one package at a time and update handover.md before every handover.

## Current status

The Composer manifest, validated configuration, PDO/bootstrap, migration runner, database job core, administrator login/session/CSRF module, SMTP account/submission/delivery cores, and send-API modules are present. Only the administrator login/dashboard/password/logout web routes are registered. Dependencies are not installed or locked, no migration has run, and PHP/MySQL/HTTPS/SMTP behavior has not been verified on this host. Read `handover.md` and module docs for exact blockers; placeholder files are not completed features.

Configure the web document root to `public/` only; the project root and storage stay private. Never upload credentials into a public directory. Resolve and review Composer dependencies on a development host, commit the lock, use a disposable database for the documented tests, and do not enable outbound/API behavior until its acceptance checks pass.

## File organization

- public/: HTTP entrypoint and static assets; it must be the only web document root.
- bootstrap/, config/, routes/: application startup and configuration.
- src/: controllers, middleware, services, mail adapters, repositories, and workers.
- templates/: future server-rendered views.
- database/migrations/: explicit versioned migrations.
- bin/: migration, preflight, administrator, and bounded worker commands.
- storage/: private logs, cache, queue content, uploads, and backups.
- tests/: unit, integration, and non-sensitive mail fixtures.
- docs/: architecture, API, operations, security, database, configuration, testing, and interface notes.

See docs/structure.md for the complete starter file inventory. No real credentials or files from the old panel are included.
