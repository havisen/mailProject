# Task 16 — tasks

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: accepted auth; contact/message links require accepted viewing/contacts. Read roadmap milestone 11, workstream migration reservations, shared platform contracts, and task placeholders. Implement only the first incomplete package per run:

- **T1:** migration 020 and prepared repository for title, notes, `todo|in_progress|done`, `low|normal|high`, optional date-only due date, completed/created/updated timestamps, stable public IDs, filters, paging.
- **T2:** service validation/state transitions. Completion sets UTC completion time; reopen clears it. Due/today/overdue uses configured display timezone without converting the stored date-only value.
- **T3:** authenticated+CSRF server-rendered list/filter/create/view/edit/complete/reopen/delete routes/templates with accessible forms, safe old input, 303/404/422 behavior.
- **T4:** optional contact/message links with fallback message snapshot. Use migration 030 only if compatible/unapplied; otherwise additive migration. Deleted contacts or unavailable cached messages must not break tasks. Add DB/service/HTTP/security tests and docs.

No task API, reminders, recurrence, calendar, assignees, teams, or automatic task creation. Escape notes/output; deleting a task never deletes contacts or email.
