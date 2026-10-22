<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Security Introduction

This starter has no implemented security controls. Empty server configuration and middleware files must never be mistaken for protection.

## Required controls

Independent administrator authentication; password hashing; secure sessions; CSRF protection for browser mutations; API-key hashing, revocation and sender scopes; encrypted mailbox credentials; verified TLS; rate limits; prepared statements; safe MIME handling; sanitized sandboxed email HTML; blocked remote content; private uploads; authenticated downloads; filename and path validation; endpoint destination restrictions; safe logs and audit events.

Reject loopback, metadata, and private endpoint destinations by default. Only an explicit operator-controlled exception may permit a known legitimate private mail endpoint. Never disable certificate validation. Never log credentials, API keys, authentication codes, reset links, or private message bodies.

Do not automatically append API authentication emails to Sent by default. A provider may independently retain sent mail. Encrypt queued API content and document deletion and backup retention. Update this document with verified controls and remaining gaps as milestones progress.
