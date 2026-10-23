# Task 14 — mailbox actions and search

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: IMAP sync/view and compose accepted. Read roadmap milestone 9, job/IMAP/message schema, operations/security/testing docs.

Implement authenticated panel-only behavior:

- Queue read/unread, flag/unflag, archive, move, and move-to-trash by stable account/folder/UIDVALIDITY/UID. Display pending/succeeded/failed states and reconcile other-client changes.
- Use IMAP MOVE when supported; fallback must copy, verify the exact target, mark only the exact source UID deleted, and avoid broad EXPUNGE. No permanent-delete feature.
- Serialize per account, honor worker budgets, make retries operation-specific/idempotent, and surface offline/UID-reset/folder-change failures safely.
- Add paginated search over synchronized metadata with explicit coverage/staleness/cap indicators. Escape bound LIKE/full-text input as appropriate; do not fetch arbitrary remote search results in a web request.
- Group conversations within one account using References/In-Reply-To/Message-ID evidence; subject alone never joins threads.
- Test targeted deletion safety, interrupted move, duplicate/replayed job, concurrent clients, UID reset, unavailable folders, search wildcards/injection/pagination, incomplete cache disclosure, and false-thread prevention.

No mailbox/action/search API and no automatic contact changes.
