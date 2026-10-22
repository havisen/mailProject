<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# DeepSeek Assignment — Basic Contacts Only

## Objective

Implement a simple contacts module for the lightweight PHP mail panel. This is a separate workstream from SMTP sending. Read AGENTS.md, handover.md, docs/workstreams.md and this assignment before changing files.

The scaffold currently has empty implementation files. Do not assume authentication, Composer, migrations, Twig or routes already work. The sender/platform contributor builds those shared pieces. Use their frozen contract; do not create a second app, login, API, dependency stack or database connection.

## Scope

Implement contact create, list, view, edit, delete, search, duplicate warnings and validation. Fields: optional name, one or more email addresses, optional phone, optional company text, and notes. A contact can be created without a mailbox connection.

No public contacts API; no email sending, SMTP, inbox, tasks, companies module, imports/exports, tags, automated contact harvesting, merge engine, CRM pipelines or user/tenant system. Linking to received messages waits until the receiving module exists. Do not fake message IDs or add mail tables now.

## Owned files and integration boundary

You own src/Controllers/ContactController.php, src/Services/ContactService.php, src/Repositories/ContactRepository.php, templates/contacts/, new routes/contacts.php, new database/migrations/010_create_contacts.php, contact-specific tests, this plan's status notes and docs/contacts-handover.md.

Do not edit bootstrap, public/index.php, routes/web.php, routes/api.php, layout.twig, shared CSS/JS, Composer, shared middleware, account/sender files or sender migrations. Provide explicit registration instructions to the integrator instead. Do not fill the old combined 005 contact/task/link placeholder; it is retired in favor of isolated migrations.

You must still update root handover.md before handover; when branches conflict the integrator merges both workstreams' factual updates. Read docs/workstreams.md for the platform contract, Twig blocks, CSRF field, route closure and migration runner format.

## Contact data contract

Create contacts and contact_emails tables only. Internal keys may be BIGINT; exposed IDs are ctc_ plus 32 lowercase hexadecimal random characters, unique and immutable. API/email/sender identifiers are not contact IDs.

contacts: internal ID, public ID, name, phone, company, notes, created_at, updated_at. Use name <= 120 characters, phone <= 50, company <= 160, notes <= 10,000. Empty optional values become null. At least one valid email is mandatory; when name is empty, display the primary email.

contact_emails: internal ID, contact FK with cascade delete, original display address, normalized address, is_primary and timestamps. Allow 1–10 emails per contact. Require exactly one primary; default the first when no primary is selected. On edit the submitted email collection replaces the old collection inside one transaction. Use a unique constraint on contact ID plus normalized address with case-sensitive/binary comparison. Different contacts may share an address; show a warning without automatically merging or blocking a legitimate shared address.

Normalize by trimming, converting an internationalized domain with IDNA, and lowercasing the domain only. Preserve the local-part case; do not apply provider-specific dot/plus rules. Defer SMTPUTF8 local parts. Reject invalid addresses, control characters and duplicate normalized addresses within one contact. Maximum normalized address length: 254 bytes.

Treat phone as optional text, not a country-specific number. Store notes as plain text and escape them on output. Use database timestamps in UTC and the platform's configured timezone for display. Use prepared statements, foreign keys and transactions for contact/email writes.

## Private browser routes

| Method | Path | Behavior |
| --- | --- | --- |
| GET | /contacts | Paginated list and q search |
| GET | /contacts/new | New contact form |
| POST | /contacts | Validate and create |
| GET | /contacts/{id} | Contact detail |
| GET | /contacts/{id}/edit | Edit form |
| POST | /contacts/{id} | Validate and update |
| POST | /contacts/{id}/delete | Delete after confirmation |

Register /contacts/new before the variable route and restrict {id} to the public ID format. The shared platform mounts these routes under administrator authentication and validates browser mutation CSRF. Templates submit hidden csrf using csrf_token. GET never creates, updates or deletes.

List: q up to 200 characters, page starting at 1, 25 records per page. Search name, company and email using escaped bound LIKE parameters; treat % and _ as literal user input. Sort by updated_at descending then public ID to stabilize paging; no body/notes search is required. Clamp page to at least 1; pages beyond the last return an empty list.

Successful create/update redirects 303 to detail; delete redirects 303 to list. Invalid form input returns 422 with field errors and safe old input. Unknown/malformed contact IDs return 404. Show a delete confirmation page or form and preserve normal keyboard/browser behavior without requiring JavaScript.

## Implementation packages

### C1 — Schema and repository

Create the two-table migration, prepared-statement CRUD, email-list persistence, search/pagination and duplicate lookup. Use the shared migration contract. Make contact/email writes atomic. Document indexes and deletion behavior.

Accept: fresh migration succeeds, parent/child rollback works on failure, duplicate rows within one contact fail safely, cross-contact shared addresses are allowed, deterministic search/pagination works on MySQL/MariaDB.

### C2 — Service and validation

Implement normalization, length/type limits, primary email rules, safe errors and duplicate-warning results. Reuse platform ValidationException. Contacts work independently from SMTP and all mail tables.

Accept: no-email, invalid-address, oversized notes, duplicate-in-contact and invalid-primary inputs fail without partial writes; shared addresses warn without merging. International domains normalize predictably.

### C3 — Controller and templates

Implement all private browser routes, list/detail/create/edit/delete forms, empty/search-no-result states, escaped content, CSRF fields and accessible labels/errors. Templates extend layout.twig title/content blocks. Add templates/contacts/show.twig and delete.twig if needed.

Accept: authenticated CRUD works; anonymous access and CSRF rejection are demonstrated using the real shared platform. Without S1/S2 record this as blocked integration, never claim full completion using a fake login.

### C4 — Tests, integration notes and handover

Add meaningful repository/service and authenticated HTTP tests. Cover SQL-injection strings, XSS in notes/names, malformed IDs, validation input preservation, search wildcards, pagination, primary replacement, atomic edits and deletion. Verify deleting contacts never affects sender tables or SMTP work.

Provide the exact dependency registrations, route mounting and navigation entry the integrator must add to platform-owned files. List migrations and commands with results. Update docs/contacts-handover.md, root handover.md and submit contact-only changed files for integration.

## Required final handover

State what works, files changed, migration applied/not applied, exact checks and results, shared-platform version/assumptions, blockers and the next step. If browser tests cannot run, say why. Never include production contacts, credentials or private mail in fixtures or docs.

End every handover with: "Read and update handover.md before handing over, and require the next contributor to do the same."
