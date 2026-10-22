<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Work Split and Integration Contract

## Current assignment

This is a planning update, not an implementation. The sender contributor owns the shared platform and outgoing mail. DeepSeek owns basic contacts. Receiving, IMAP, replies, and tasks remain deferred. No contributor should modify the old one-way sender.

Read this document, AGENTS.md, handover.md, and your workstream plan before starting. The current detailed plans are docs/sender-plan.md and docs/contacts-deepseek.md. They narrow the next delivery slices of ROADMAP.md without removing its later features.

## Ownership

| Area | Owner | Boundary |
| --- | --- | --- |
| Composer, bootstrap, PDO, Twig, configuration, public index, layout, authentication, CSRF, shared errors, migrations runner, tests harness | Sender/platform contributor | Publish the platform contract below before contact integration |
| SMTP accounts, credentials, API keys, submission queue, SMTP worker, send endpoint, Outbox, diagnostics | Sender/platform contributor | No incoming email API or IMAP dependency |
| Contact controller/service/repository, contact templates, contact routes, contact migration, contact tests | DeepSeek | No sender, account, authentication, or global dependency edits |
| Contacts/send-form selection integration | Sender/platform contributor after contacts merge | Optional later integration, not a contact acceptance dependency |
| Receiving, tasks, message/contact links, remote Sent copies | Future work | Do not implement in either current track |
| Root roadmap, root handover, shared docs and merge resolution | Integrating contributor | Merge factual updates from both branches; preserve both workstreams |

## Shared platform contract — freeze in sender package S1

- PHP 8.4, Slim 4, Twig, PDO MySQL, PSR-7 responses; PSR-4 namespace App\ mapped to src/. Use Composer-locked compatible versions.
- The platform injects PDO into repositories, repositories into services, and services plus Twig into controllers. No global PDO or contact-owned connection/bootstrap.
- Repositories use PDO prepared statements and associative rows. Services own validation and local write transactions. Controllers return PSR-7 responses.
- Authenticated browser route groups enforce the common AuthMiddleware. All browser mutations additionally require CsrfMiddleware and hidden field csrf, compared against the session token. Templates receive csrf_token. No CSRF exemptions for contacts.
- routes/contacts.php returns a closure accepting Slim\Routing\RouteCollectorProxy. The platform loads it inside the protected browser group only when the module's dependencies and callable route registration are ready. It must not expose contacts under /api.
- templates/layout.twig provides title and content blocks. Contact templates extend it. Shared app.css and app.js are platform-owned. Contacts use ordinary forms and accessible semantic HTML without a JavaScript build dependency.
- Shared validation exception App\Exceptions\ValidationException exposes field errors and safe old input. Validation responses use HTTP 422. Missing records use 404. Successful browser writes redirect with 303.
- Migration files return a closure accepting PDO and performing their DDL. The runner tracks the filename and checksum after success, uses a migration lock, stops on error, and never assumes MySQL DDL is transactionally reversible. A failed migration requires inspection before retry.
- Dates/timestamps persist in UTC. The panel has one administrator and no tenant IDs or organization concepts.
- Tests use the platform's disposable MySQL/MariaDB database. A SQLite-only result is not acceptance evidence.

These are planned contracts, not existing implementation. DeepSeek can implement contact repository/service logic and module tests against the contract immediately. Full authenticated HTTP verification waits for S1/S2; do not invent a second platform to bypass that dependency. If the actual framework API requires a contract correction, the integrating contributor documents it before changing either module.

## Database ownership

- Sender uses migrations 001, 002, 003 and 006 already reserved in the starter, plus later sender-specific files only if needed.
- DeepSeek creates 010_create_contacts.php, owning contacts and contact_emails only.
- The empty combined 005_create_contacts_tasks_and_links.php is retired as a placeholder: leave it untouched and have the migration runner ignore zero-byte placeholder files. Do not implement it alongside 010.
- 004_create_mail_cache_and_drafts.php stays empty for later mail work. Future tasks use 020; future email/contact/task links use 030.
- Never renumber or rewrite a migration after it has been applied. Numeric gaps are intentional.

## Parallel development and integration

1. Use separate branches/copies for sender and contacts; agree this contract before shared integration.
2. Sender delivers S1/S2. DeepSeek may develop contact-local code earlier but records the missing integration prerequisite.
3. Each contributor edits their owned implementation files and own plan/handover section. DeepSeek supplies docs/contacts-handover.md; sender supplies docs/sender-handover.md.
4. Both must also update root handover.md before handing over. If edits happen on branches, the integrator merges both updates; never replace another contributor's facts wholesale.
5. Integrator registers contacts routes/dependencies and adds navigation in platform-owned files. Run contact and sender regression checks together.
6. Update ROADMAP.md, root handover.md, and CHANGELOG.md with tested results. An empty placeholder is not completed functionality.

Every handover must explicitly require the next contributor to read and update handover.md and pass that same requirement onward.
