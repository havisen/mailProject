<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Lightweight Mail Panel

Project starter for a private cPanel-hosted mail panel. It contains documentation, mostly empty implementation placeholders, a minimal public index page, and an isolated SMTP sending-account core. It is not a working mail application and must not be deployed as a secured service yet.

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

## Starter status

Most implementation files remain intentionally empty. The exceptions are the SMTP account cipher, endpoint policy, migration, repository, service, connection-test contract, and temporary module test runner described in `docs/accounts.md`. No dependencies are installed. There is no login, API, migration runner, worker, usable panel, or real mail connection yet. Empty configuration files are placeholders, not valid deployment configuration.

Do not run Composer until its manifest is implemented. Configure the future web document root to public/ only; the project root and storage must stay private. Do not upload credentials into any public directory.

## File organization

- public/: future HTTP entrypoint and static assets.
- bootstrap/, config/, routes/: application startup and configuration.
- src/: controllers, middleware, services, mail adapters, repositories, and workers.
- templates/: future server-rendered views.
- database/migrations/: explicit versioned migrations.
- bin/: future maintenance and worker commands.
- storage/: private logs, cache, queue content, uploads, and backups.
- tests/: unit, integration, and non-sensitive mail fixtures.
- docs/: architecture, API, operations, security, database, configuration, testing, and interface notes.

See docs/structure.md for the complete starter file inventory. No real credentials or files from the old panel are included.
