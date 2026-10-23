<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Security Introduction

This scaffold is not deployable as a verified secured application. The foundation validates startup secrets and private paths, creates private redacted JSON logs, uses native PDO prepares/strict SQL mode, returns internal request IDs, and fails HTTP startup without details. Milestone 2 now implements the administrator login/session/CSRF boundary and protected authentication routes, but PHP/database/HTTPS acceptance and dependency locking are still absent. Other feature routes remain unwired and must not be treated as protected until explicitly registered behind the middleware.

The request-ID middleware intentionally ignores caller-supplied IDs. Logging redacts keys whose names imply authorization, passwords, secrets, tokens, fingerprints, bodies, or content and removes Bearer values from strings. Code must still avoid logging request payloads, private messages, recipient lists, credentials, API/idempotency keys, or raw SMTP/IMAP transcripts. Only `public/` may be web-accessible, and migration/preflight operations are CLI-only.

## Required controls

Independent administrator authentication; password hashing; secure sessions; CSRF protection for browser mutations; API-key hashing, revocation and sender scopes; encrypted mailbox credentials; verified TLS; rate limits; prepared statements; safe MIME handling; sanitized sandboxed email HTML; blocked remote content; private uploads; authenticated downloads; filename and path validation; endpoint destination restrictions; safe logs and audit events.

Reject loopback, metadata, and private endpoint destinations by default. Only an explicit operator-controlled exception may permit a known legitimate private mail endpoint. Never disable certificate validation. Never log credentials, API keys, authentication codes, reset links, or private message bodies.

Do not automatically append API authentication emails to Sent by default. A provider may independently retain sent mail. Encrypt queued API content and document deletion and backup retention. Update this document with verified controls and remaining gaps as milestones progress.

## Implemented administrator boundary

- One administrator is provisioned or recovered only from CLI. Passwords are valid UTF-8, at least 14 characters and at most 1,024 bytes, and use PHP Argon2id.
- Login errors are generic and unknown users take a password-verification path. Database throttles use separate HMAC scopes for normalized email and the server-observed IP; forwarded-address headers are deliberately ignored.
- A successful login replaces any supplied old session with a random 256-bit token. The database stores only SHA-256 token hashes. Session cookies are always Secure, HttpOnly, SameSite=Strict, path `/`; idle and absolute expiry plus administrator security version are checked per protected request.
- Login has a separate HttpOnly challenge cookie and action-bound signed token. Authenticated mutations require action/session/expiry-bound CSRF. Password change/recovery increments security version and revokes all sessions; browser change receives exactly one replacement session.
- Authentication/private HTML is `no-store`. Global headers provide restrictive CSP, frame denial, no-referrer, no-sniff, permissions restrictions, and HSTS. The dashboard is intentionally minimal.
- Audit events use a fixed allow-list and safe identifiers only. Redaction now treats generic `_key` configuration fields as secrets. Passwords, raw tokens, private request bodies, and authorization values are never audit data.

Threats still requiring operational evidence include Secure-cookie behavior through the real HTTPS proxy, concurrent throttle races, browser tests, session/throttle cleanup, dependency supply-chain review, backup/restore, and web-server denial of non-public files. Do not disable Secure cookies, CSRF, TLS validation, or throttling to bypass a failed setup.

## Implemented in the SMTP account core

- `CredentialCipher` uses Sodium secretbox with versioned key IDs and account/field context binding. Keys remain outside the database and document root. Empty credentials are rejected.
- `EndpointPolicy` requires an exact approved host, restricts SMTP to 465/implicit TLS or 587/STARTTLS, resolves DNS, and blocks special/private destinations unless the matched endpoint has a narrow operator-controlled exception.
- `AccountRepository` omits encrypted credentials from panel reads and uses prepared statements.
- `SmtpConnectionTester` exposes only fixed safe error categories through a transport adapter contract and never sends message DATA.

These controls have not been runtime-tested on this machine. The real SMTP adapter must preserve certificate verification and must not log provider responses containing sensitive details. See `docs/accounts.md` and `docs/handovers/05-smtp-sending-accounts.md`.

## Implemented in outgoing persistence

`QueuedContentCipher` uses authenticated Sodium encryption with per-submission/per-field context and separately configured content keys. Immutable snapshots include envelope Bcc but database rows and durable results do not expose plaintext recipients or bodies. Recipient addresses are individually encrypted. Submission validation rejects header newlines/NUL, unapproved From identities, excessive recipients/content, API attachments, remote URLs, and arbitrary paths. Only future owned `blob_` references are syntactically accepted for internal panel use; blob storage is not implemented.

`detailForAuthenticatedUi()` returns decrypted private content and must only be wired behind shared administrator authentication. No controller/route exists, so CSRF and authorization are not yet verified. Do not log its return value. Database backups contain ciphertext but remain sensitive; losing content keys prevents recovery, while leaking them with a backup reveals queued content.

The PHPMailer adapter forces verified peer/hostname TLS, rejects self-signed certificates, disables debug output, clears the in-memory password after the attempt, and persists only safe outcome categories/numeric facts. Bcc is envelope-only. There is no configuration escape hatch for certificate verification.

## Implemented API-key boundary

API credentials use random 256-bit secret material plus a collision-resistant nonsecret prefix. Only Argon2id hashes are stored. Authentication performs a dummy hash verification for unknown prefixes, accepts only one Bearer header, rejects token-like query parameters, and yields an explicit `mail:send`/account principal. Revocation takes effect before replay authorization. Replacement supports an administrator-controlled overlap; it does not silently revoke the old key or cancel queued submissions. Safe audit events contain lifecycle type and key reference only. Shared administrator authentication/CSRF still must protect the settings actions before the template is routable.

The send API HMACs both the endpoint/idempotency identifier and canonical payload with a distinct server-held fingerprint key, reducing offline guessing of low-entropy caller identifiers or authentication-message content. Account scope is checked before replay lookup. Responses omit Bcc, bodies, Message-ID, provider errors, and status URLs; controller responses use `Cache-Control: no-store`. Strict media type/size/field rules and IDNA normalization precede persistence. No CORS headers are emitted. The route remains unwired, so these controls are not yet end-to-end verified.
