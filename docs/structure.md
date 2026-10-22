<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Starter File Inventory

All implementation files are empty except public/index.php. Markdown files contain introductory documentation and the roadmap. Empty .gitkeep files preserve private storage/fixture directories. No vendor files, secrets, production data, or dependency lockfile are included. Generate and commit a lockfile only after implementing the Composer manifest.

```text
.env.example
.gitignore
AGENTS.md
CHANGELOG.md
README.md
ROADMAP.md
bin/console.php
bin/worker.php
bootstrap/app.php
bootstrap/dependencies.php
composer.json
config/app.php
config/database.php
config/mail.php
config/queue.php
config/security.php
database/migrations/001_create_admin_and_jobs.php
database/migrations/002_create_accounts.php
database/migrations/003_create_submissions_and_api_keys.php
database/migrations/004_create_mail_cache_and_drafts.php
database/migrations/005_create_contacts_tasks_and_links.php
database/migrations/006_create_audit_events.php
docs/api.md
docs/architecture.md
docs/configuration.md
docs/contacts-deepseek.md
docs/contacts-handover.md
docs/database.md
docs/interface.md
docs/openapi.yaml
docs/operations.md
docs/security.md
docs/sender-handover.md
docs/sender-plan.md
docs/structure.md
docs/testing.md
docs/workstreams.md
handover.md
phpunit.xml
public/.htaccess
public/assets/css/app.css
public/assets/js/app.js
public/index.php
routes/api.php
routes/web.php
src/Controllers/AccountController.php
src/Controllers/Api/SendMailController.php
src/Controllers/AuthController.php
src/Controllers/ContactController.php
src/Controllers/MailController.php
src/Controllers/SettingsController.php
src/Controllers/TaskController.php
src/Mail/HtmlSanitizer.php
src/Mail/ImapAdapter.php
src/Mail/MessageParser.php
src/Mail/SmtpTransport.php
src/Middleware/ApiKeyMiddleware.php
src/Middleware/AuthMiddleware.php
src/Middleware/CsrfMiddleware.php
src/Middleware/RateLimitMiddleware.php
src/Middleware/SecurityHeadersMiddleware.php
src/Repositories/AccountRepository.php
src/Repositories/ContactRepository.php
src/Repositories/JobRepository.php
src/Repositories/MessageRepository.php
src/Repositories/SubmissionRepository.php
src/Repositories/TaskRepository.php
src/Security/CredentialCipher.php
src/Security/EndpointPolicy.php
src/Services/AccountService.php
src/Services/ApiKeyService.php
src/Services/AuthService.php
src/Services/ContactService.php
src/Services/DraftService.php
src/Services/MessageService.php
src/Services/SubmissionService.php
src/Services/SyncService.php
src/Services/TaskService.php
src/Workers/CleanupWorker.php
src/Workers/SendWorker.php
src/Workers/SyncWorker.php
storage/backups/.gitkeep
storage/cache/attachments/.gitkeep
storage/cache/messages/.gitkeep
storage/logs/.gitkeep
storage/queue/.gitkeep
storage/uploads/.gitkeep
templates/accounts/form.twig
templates/accounts/index.twig
templates/auth/login.twig
templates/contacts/form.twig
templates/contacts/index.twig
templates/errors/error.twig
templates/layout.twig
templates/mail/compose.twig
templates/mail/drafts.twig
templates/mail/inbox.twig
templates/mail/message.twig
templates/mail/outbox.twig
templates/settings/api-keys.twig
templates/settings/diagnostics.twig
templates/settings/index.twig
templates/tasks/form.twig
templates/tasks/index.twig
tests/Fixtures/mail/.gitkeep
tests/Integration/ContactTaskLinksTest.php
tests/Integration/SendApiTest.php
tests/Integration/SyncTest.php
tests/Unit/CredentialCipherTest.php
tests/Unit/MessageParserTest.php
tests/Unit/SubmissionStateTest.php
tests/bootstrap.php
```
