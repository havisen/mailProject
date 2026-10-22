<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Testing Introduction

Test files are empty placeholders. No application tests have run. Add focused tests as real behavior is implemented rather than tests that merely assert the existence of placeholders.

## Critical scenarios

- Fresh install, repeated migrations, private-file access, and administrator recovery.
- Session expiry, CSRF rejection, API scopes, key revocation, and concurrent idempotency.
- Database failure before acceptance, delayed cron, expired queued mail, SMTP rejection, and uncertain acceptance.
- Worker interruption, duplicate-send prevention, separate Sent-copy retry, and safe restore.
- UIDVALIDITY change, arrivals during backfill, remote flag/deletion changes, and interrupted synchronization.
- Multipart and malformed MIME, encoded headers, oversized bodies, HTML injection, trackers, attachment access, and path traversal.
- Reply headers, reply-all recipients, Bcc privacy, draft persistence, and double-click Send.
- Contact/task links surviving cache cleanup and deletion; task due dates in the configured timezone.

Use disposable mailboxes and databases. Run real provider tests against cPanel and a second standard provider before release. Never use private mail or production secrets as fixtures. Record exact commands, results, and unverified areas in handover.md.
