<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# SMTP Sending Accounts

Status: the platform-independent account core is implemented. HTTP, database-runner, provider, and panel integration remain unverified and blocked on sender packages S0–S2.

## Scope

An SMTP sending account contains an operator-approved SMTP endpoint, credentials, sender identity, enabled state, and provider limits. It does not require IMAP. Receiving settings must be introduced by a later additive migration and must not duplicate the `mail_accounts` table.

This module does not send mail. Submission persistence, delivery, API keys, and the send-only API belong to later sender packages.

## Credential storage

`App\Security\CredentialCipher` stores credentials in this format:

```text
v1.<key-id>.<base64url-nonce>.<base64url-ciphertext>
```

It uses Sodium secretbox with a random nonce. When a context is supplied, keyed BLAKE2b derives a context-specific secretbox key from the master key. SMTP passwords use `acct:<public-id>:password`, preventing a ciphertext copied from one account from authenticating in another account context.

Master keys are base64-encoded 32-byte values. They must be provisioned outside the database and web document root, backed up separately, and never logged. New encryption uses `security.active_key_id`; old values remain decryptable while their key IDs remain in `security.credential_keys`.

The repository never includes `password_enc` in normal panel reads. A blank password during edit preserves the existing ciphertext. Only worker and connection-test code may request and decrypt the credential.

## Endpoint policy

`App\Security\EndpointPolicy` requires an exact operator-approved hostname and permits only:

- port 465 with implicit TLS;
- port 587 with STARTTLS.

DNS results are checked before connection. Loopback, private, link-local, carrier-grade NAT, multicast, reserved, documentation, and common metadata ranges are rejected. A single approved endpoint can set `allow_private` only for a known private cPanel host. TLS certificate verification must still remain enabled.

## Account lifecycle

`AccountService` validates and normalizes input, allocates an immutable `acct_` public ID, encrypts the password, and delegates persistence to prepared statements in `AccountRepository`.

Disabling an account prevents new work and records a visible pause reason. Later queue integration must pause existing queued submissions until re-enabled or expired; it must not silently change sender identity or resend messages. Deletion requires explicit controller/UI confirmation and later submission-integrity rules.

## Connection checks

`SmtpConnectionTester` performs no network work itself. Its `SmtpProbe` adapter contract is for connect, TLS, authentication, and optional sender authorization checks without sending message DATA. Results are normalized to fixed safe categories: `dns`, `connectivity`, `tls`, `auth`, `sender_auth`, `quota`, or `protocol`. Provider responses and credentials must not be shown or logged.

A PHPMailer-backed probe is still required after Composer is configured. Sending a test email must be a separate, explicit action through the durable submission queue.

## Integration still required

- Apply `database/migrations/002_create_accounts.php` through the checksum-tracked migration runner.
- Register the cipher, endpoint policy, repository, account service, and real SMTP probe in the dependency container.
- Add authenticated and CSRF-protected account routes, controller actions, forms, and navigation.
- Ensure the UI never redisplays stored secrets and treats a blank edit password as unchanged.
- Run the repository tests on disposable MySQL/MariaDB and run controlled real-provider TLS/authentication checks.

## Local checks

The dependency-free test runner is:

```text
php tests/Unit/run-unit.php
```

It contains 40 checks for encryption, endpoint restrictions, service behavior, and safe SMTP probe classification. It has not been executed on the current Windows machine because PHP is not installed or available on `PATH`.
