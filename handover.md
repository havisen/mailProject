<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Project Handover

## Current state

Documentation and empty starter structure only. No application features have been implemented. public/index.php is a minimal static introduction page. ROADMAP.md records the implementation sequence and acceptance gates.

## Latest changes

Split the next work into docs/sender-plan.md (sender/platform packages S0–S9) and docs/contacts-deepseek.md (DeepSeek contacts packages C1–C4). Added docs/workstreams.md for ownership, contracts, migration reservations, and integration order, plus module-specific handovers. Updated roadmap/README/database documentation and packaged the updated starter. No application code changed; implementation placeholders remain empty.

Repository setup began on 2026-10-23: initialized a local Git repository on `main` and added a root `.gitignore` covering environment files, Composer dependencies, test output, and private runtime storage. The GitHub remote is not yet configured because the exact owner/repository URL has not been verified.

The external API is strictly POST /api/v1/mail/send. No read/status, inbox, contact/task, or OTP endpoints are planned. The sender slice does not depend on IMAP and defers drafts, attachments, replies and Sent APPEND. DeepSeek owns contacts only; the sender contributor owns shared authentication/bootstrap and integrates contacts after publishing that contract.

## Architecture and decisions

Read docs/architecture.md, docs/api.md, and ROADMAP.md. Target: cPanel, PHP 8.4, MySQL/MariaDB, one administrator, standard IMAP/SMTP providers, cron jobs, and a separate deployment from the old sender. Package compatibility and hosting capabilities remain unverified.

## Environment and configuration

No runtime or hosting environment has been configured. config files and .env.example are empty. No secrets are present. See docs/configuration.md for the intended categories and docs/operations.md for setup responsibilities.

## Database

No schema, migrations, or production database exists in this starter. Migration files are empty placeholders. Do not treat their names as executed migrations.

## Verification

Original starter and updated work-split packaging were checked for file inventory, required documentation, placeholder emptiness, handover comments, and ZIP integrity. Workstream documentation links and strict send-only API boundary were reviewed. No application, dependency, provider, database, security, or hosting tests have run.

Repository preparation checks: reviewed hidden and large files, scanned tracked content for common secret assignments, staged the complete tree, and ran `git diff --cached --check`. No credential values or oversized files were identified. Remote connectivity and push remain unverified.

## Known issues and limitations

The scaffold is deliberately nonfunctional. composer.json is empty and must be written before Composer can be used. .htaccess and other configuration files provide no protection yet. The index page does not prove PHP or mail connectivity. Backup and recovery behavior remains unimplemented.

The GitHub remote URL is still required. GitHub CLI is unavailable and the accessible browser session is signed out, so the private repository cannot be identified reliably by name alone.

## Next step

Configure `origin` using the exact GitHub repository URL and push `main`. Then begin S0 in docs/sender-plan.md, checking PHP web/CLI, required extensions, private storage, database grants, one-minute cron, TLS and SMTP 465/587. Then implement S1/S2 and publish the shared platform contract. IMAP feasibility remains future receiving work and does not block the sender.

DeepSeek: read docs/contacts-deepseek.md and docs/workstreams.md; begin C1/C2 using isolated contact files and migration 010. Authenticated HTTP verification waits for S1/S2. Do not edit sender or shared platform files. Every contributor updates root handover.md and their own workstream handover; the integrator merges both factual updates.

## Required instruction for the next contributor

Read handover.md before starting, update it before handing work over, and explicitly tell the next contributor to do the same. Record implementation changes, documentation, configuration names, migrations, checks and results, known issues, decisions, and the exact next step. Never include secret values or private mail contents.
