# Task 15 — contacts

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Read `docs/contacts-deepseek.md`, `docs/workstreams.md`, `docs/contacts-handover.md`, and only direct contact/platform dependencies. Implement only the first incomplete package per run, then stop:

- **C1:** migration 010 plus prepared repository CRUD, email collection transactions, duplicate lookup, search/pagination.
- **C2:** validation/normalization, primary email rules, safe old input/errors, shared-address warnings without merge.
- **C3:** accessible authenticated/CSRF controller, routes, templates, 303/404/422 behavior; integration owner performs shared bootstrap/navigation edits.
- **C4:** disposable-DB/service/authenticated-HTTP security tests, docs, and registration handover.
- **C5:** only after safe message viewing exists, add reviewed create/link-from-mail and synchronized contact history with minimal message snapshots. Use migration 030 only if compatible and unapplied; otherwise add an additive migration. Contact deletion never deletes mail.

Follow the detailed assignment over this summary when they differ. No contacts API, automatic harvesting, bulk import, merge engine, SMTP changes, tasks, or tenant system. Use migration 010, never retired migration 005. Test XSS/SQL strings, wildcard search, atomic email replacement, shared addresses, malformed IDs, deletion isolation, link/cache survival, anonymous denial, and CSRF.
