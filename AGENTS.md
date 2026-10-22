<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Contributor Instructions

Read this file, handover.md, and ROADMAP.md before changing the project.

## Boundaries

- Build one roadmap milestone at a time.
- Keep the old sending application separate and unchanged.
- The external API is only for sending email. Do not add receiving, inbox, contacts, tasks, OTP-generation, or OTP-verification endpoints.
- Keep contacts, tasks, reading, replies, and account management inside the authenticated panel.
- Do not implement future features just because placeholders exist.
- Never commit secrets, real authentication codes, private email, uploaded attachments, or production logs.
- Do not deploy this scaffold as a working or secured application.

## Implementation principles

Use server-rendered PHP, small JavaScript enhancements, MySQL/MariaDB, and bounded cron jobs. Keep business logic shared through services. Only public/ may be web-accessible. Use explicit migrations, prepared statements, encrypted credentials, safe HTML rendering, and verified TLS.

## Completion and handover

Run checks relevant to the change. Record exact checks and results; explicitly name unverified behavior. Update the appropriate docs, ROADMAP.md, and handover.md. Every handover must tell the next contributor to read and update handover.md and pass the same instruction onward.

Do not mark placeholders implemented. Documentation is part of each milestone's completion.
