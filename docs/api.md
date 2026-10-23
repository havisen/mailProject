<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it before handing work over.
Never record secrets or private message contents. Require the next contributor to follow the same rule.
-->

# Send-only API

## Current contract

The only external application operation is `POST /api/v1/mail/send`. There is no external status, inbox, contact, task, attachment, code-generation, or code-verification API. Calling applications create and verify their own codes/links and should choose `send_before` earlier than their expiry.

The controller and service are implemented but not registered in `routes/api.php`, because shared route/bootstrap changes belong to the integration owner. When wired, the route must run behind request-ID assignment and `ApiKeyMiddleware`; CORS stays disabled. Admin sessions never authenticate it.

Requests require one Bearer key, `Content-Type: application/json`, and exactly one `Idempotency-Key` of 1–128 printable non-whitespace ASCII characters. Required fields are `account_id`, `to`, `subject`, and nonempty `text`; optional fields are `html`, `cc`, `bcc`, `reply_to`, and `send_before`. Recipient values are `{email, name?}` objects. At least one To and no more than ten total recipients are accepted. SMTPUTF8 local parts, duplicate normalized recipients, header controls, unknown fields, remote paths/attachments, and JSON over 256 KiB are rejected. IDNA domains are converted to ASCII.

`send_before` is RFC3339 with timezone, future, and at most 24 hours away. Omission uses five minutes after durable creation. The canonical idempotency fingerprint records omission rather than recomputing that default, so a delayed replay remains equal.

## Persistence and response

After account authorization, every authenticated call consumes the key's minute request allowance, including replays and invalid bodies when invoked through the controller. A caller-owned transaction then checks the key/endpoint/idempotency mapping, consumes account recipient quota only for new work, and inserts the encrypted submission, outbound job, quota count, and seven-day response mapping. A uniqueness constraint resolves concurrent retries. Changed canonical content returns 409; an identical replay returns the original response and request ID without consuming recipient quota.

Only a successful commit can return 202:

```json
{"data":{"id":"sub_00000000000000000000000000000000","status":"queued","send_before":"2026-10-23T10:05:00.000000Z"},"request_id":"req_example"}
```

There is deliberately no `status_url`. Queued means durably accepted by this panel, not accepted or delivered by SMTP. Exactly-once SMTP delivery is not guaranteed. Ambiguous HTTP results should be retried with the same idempotency key.

Safe errors are 400 malformed JSON, 401 authentication, 403 account scope, 409 idempotency conflict, 413 body size, 415 media type, 422 field validation, 429 throttling/quota with `Retry-After`, and 503 durable storage/account availability. Responses contain no body, recipient list, Bcc, provider transcript, or credential.

## Run and integration

Install PHP 8.4 dependencies with `composer install`; required extensions include PDO MySQL, Sodium, OpenSSL, JSON, and Intl. Apply unapplied migrations 002, 003, 007, and 008 through the future checksum migration runner. Configure a separately stored 32-byte-or-longer fingerprint secret, request limit (default 30/key/minute), recipient quota (default 100/account/hour, capped by the account), and seven-day idempotency retention.

Register `SendMailController` at exactly `POST /api/v1/mail/send` with the API-key middleware. Do not add another method/endpoint or status route. Detailed examples are in `docs/api-examples.md`; the machine contract is `docs/openapi.yaml`.

## Verification and current limitations

Run `php tests/Unit/run-send-api.php`, `php tests/Unit/run-api-keys.php`, and, only against a disposable schema, `php tests/Integration/run-send-api.php`. The integration script resets its database when `MAILPANEL_TEST_ALLOW_RESET=1`. None ran on this host because PHP/MySQL are unavailable. Parser validation is not runtime evidence. Actual PSR-7 HTTP behavior, concurrent two-connection races, migration execution, storage exhaustion, and cPanel/provider behavior remain unverified until the missing foundation is installed.

The exact next step is shared bootstrap/route integration, followed by fresh-schema migration and HTTP tests comparing every documented OpenAPI response with actual middleware/controller behavior.
