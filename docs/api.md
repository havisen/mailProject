<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Sending API Introduction

## Strict boundary

The external API only submits outgoing email. No inbox reading, incoming messages, attachments download, contacts, tasks, mailbox administration, OTP creation, or OTP verification. The caller owns authentication code/link generation, expiry, and verification. All other product features are used through the authenticated panel.

## Credential interface implemented; send endpoint pending

POST /api/v1/mail/send is the sole external application endpoint. Status is visible inside the panel; this starter does not plan an external status-reading endpoint. This supersedes the earlier roadmap's optional submission-status endpoint and mail:status permission.

Use Authorization: Bearer <key>, Content-Type: application/json, and a required Idempotency-Key. Assignment 09 now implements Argon2id-hashed, revocable keys restricted to allowed sending accounts with only `mail:send`. Bearer credentials in query strings and admin-session authentication are rejected. The route/controller remains Assignment 10. Never expose keys in browser JavaScript.

## Planned request

Required: account_id, to, subject, text. Optional: html, cc, bcc, reply_to, send_before. From is determined by the permitted account identity. No API attachments in version 1. Maximum 10 recipients, 200 subject characters, and 256 KiB request body. Reject header injection, unknown fields, and invalid values.

send_before defaults to five minutes after submission; explicit values must be in the future and within 24 hours. The caller should choose a deadline earlier than its token expiry. Delivery may still be delayed after SMTP acceptance.

## Planned response and idempotency

Return 202 with a submission ID, queued status, deadline, and request ID after durable persistence. Do not return a status_url. A queued response does not guarantee SMTP acceptance or delivery. Same API key, idempotency key, and canonical content returns the original submission response. Different content with the same key returns 409. Authorize before returning any replay. Enforce concurrency with a database uniqueness constraint. Retain idempotency records seven days.

Errors: 400 malformed JSON; 401 invalid/revoked key; 403 unauthorized sender; 409 idempotency conflict; 413 too large; 415 unsupported content type; 422 invalid fields; 429 rate limited with Retry-After; 503 cannot durably accept. Return stable error codes and request IDs without raw provider errors or message contents.

Initial configurable limits: 30 requests per minute per key and 100 accepted recipients per hour per account, subject to provider limits. Keep CORS disabled by default. Write docs/openapi.yaml when implementing this contract; that placeholder is intentionally empty.

## Submission-service integration status

The durable submission service now provides `createInCurrentTransaction()` and post-commit confirmation so the future API layer can atomically persist its own scoped idempotency record, one submission, and one outbound job. It does not store API idempotency keys and no route/controller exists yet. `SubmissionResult` contains no recipients, Bcc, body, or external status URL. Keep `docs/openapi.yaml` empty until the real authenticated endpoint is implemented and verified.
