<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Panel Interface Introduction

The application uses server-rendered templates and small progressive enhancements. The index currently contains only a project introduction, not an implemented interface.

## Planned areas

Login; mail account setup and independent IMAP/SMTP diagnostics; Inbox and folder list; safe message view and downloads; compose/reply/forward; drafts and Outbox; contacts; tasks; API-key management; operational diagnostics; settings.

Contacts include name, email addresses, optional phone, company text, and notes. Tasks include title, notes, todo/in_progress/done status, low/normal/high priority, optional date-only due date, and completion time. Contacts and tasks can link to mail without being exposed through the API.

Show queued, pending, stale, failed, expired, and uncertain states honestly. Preserve form input after validation errors. Show draft save status. Provide keyboard access, form labels, readable contrast, mobile layouts, loading/empty/error states, and confirmation for destructive actions. Do not claim inbox search includes mail that has not synchronized.
