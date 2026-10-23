# DeepSeek v4.1 Flash prompt order

Run one prompt at a time in this order. Tell DeepSeek to execute the named file; each task loads `00-COMMON.md` itself and stops after one milestone or sub-package.

For monitored runs, launch every task through `RUN-ONE-AND-REPORT.md`. It limits DeepSeek to one package and produces a consistent evidence packet for Codex review before the next task begins.

1. `01-RUNTIME-FOUNDATION-AUTH-VERIFY.md`
2. Repository-root `DEEPSEEK-V4.1-FLASH-OUTBOUND-INTEGRATION.md` repeatedly until gates A–E are accepted
3. `09-CLEANUP-BACKUP-SENDER-RECOVERY.md`
4. `10-OUTBOUND-RELEASE-VERIFY.md`
5. `11-IMAP-SYNCHRONIZATION.md`
6. `12-SAFE-MESSAGE-VIEWING.md`
7. `13-COMPOSE-DRAFTS-REPLIES.md`
8. `14-MAILBOX-ACTIONS-SEARCH.md`
9. `15-CONTACTS.md` repeatedly until C1–C5 are accepted
10. `16-TASKS.md` repeatedly until T1–T4 are accepted
11. `17-OPERATIONS-RELEASE.md` repeatedly until O1–O4 are accepted

Do not skip a blocked prerequisite. A prompt may implement safe independent work but must report missing runtime, database, mailbox, provider, or deployment evidence honestly.
