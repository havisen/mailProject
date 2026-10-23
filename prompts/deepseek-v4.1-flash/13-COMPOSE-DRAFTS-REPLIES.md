# Task 13 — compose, drafts, replies, and forwarding

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: durable outbound and safe viewing accepted. Read roadmap milestone 8, submission/security/database/configuration docs, compose/draft placeholders, and message contracts.

Implement authenticated panel workflows using the existing `SubmissionService`:

- Durable local drafts with explicit save state, optimistic/concurrent edit safety, refresh recovery, account identity selection, To/Cc/Bcc, subject, plain text, optional sanitized HTML, and private owned attachment blobs.
- Never accept remote URLs or arbitrary paths. Validate ownership, size/MIME/total provider limits; API attachments remain forbidden. Clean abandoned blobs with bounded retention.
- Send creates one immutable submission/job using a browser operation ID; double-click/refresh queues once. Preserve Bcc envelope privacy and sender identity.
- Reply uses Reply-To; reply-all removes self/duplicates and handles To/Cc correctly. Add safe quoting plus bounded In-Reply-To/References. Forwarding attaches/quotes only explicit user-selected content; never silently forward attachments.
- Keep SMTP acceptance and Sent-copy state separate. Implement configured Sent behavior without duplicating provider-saved copies or resending SMTP when APPEND fails.
- Test drafts, concurrent edits, double-submit, recipient/header injection, attachment access/cleanup, reply-all, thread headers, Bcc, sender switching, expiry, and Sent failure.

Do not add external compose/status APIs or rich-editor dependencies.
