<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Panel Interface Introduction

The application uses server-rendered templates and small progressive enhancements. Milestone 2 now provides an administrator login page, a minimal protected dashboard, password change, and logout. Pages use Twig autoescaping, labeled forms, generic login errors, action-specific CSRF, no-store caching, and no client-side dependency. PHP/database/HTTPS behavior remains unverified on the current host, so this is not a deployable finished interface.

## Implemented routes

- `GET /login`: issues a separate login-CSRF challenge; an already authenticated session redirects home.
- `POST /login`: authenticates with generic errors and returns 429/`Retry-After` when blocked.
- `GET /`: minimal protected dashboard with password and logout actions.
- `GET|POST /settings/password`: protected password form; POST requires CSRF and replaces all sessions with one fresh session.
- `POST /logout`: protected, CSRF-required, clears and revokes the session.

Anonymous protected page reads redirect to login. Protected mutation requests do not redirect and instead return 401/403 as appropriate. No other planned panel section is registered yet.

## Planned areas

Login; mail account setup and independent IMAP/SMTP diagnostics; Inbox and folder list; safe message view and downloads; compose/reply/forward; drafts and Outbox; contacts; tasks; API-key management; operational diagnostics; settings.

Contacts include name, email addresses, optional phone, company text, and notes. Tasks include title, notes, todo/in_progress/done status, low/normal/high priority, optional date-only due date, and completion time. Contacts and tasks can link to mail without being exposed through the API.

Show queued, pending, stale, failed, expired, and uncertain states honestly. Preserve form input after validation errors. Show draft save status. Provide keyboard access, form labels, readable contrast, mobile layouts, loading/empty/error states, and confirmation for destructive actions. Do not claim inbox search includes mail that has not synchronized.
