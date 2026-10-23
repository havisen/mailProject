# Run one task and return a review report

Use this wrapper with exactly one target task. Replace `<TARGET_PROMPT>` in the launch message with a repository-relative prompt path from this directory's `README.md`.

## Execution contract

1. Read this file, `<TARGET_PROMPT>`, and every file that target explicitly requires. Follow `prompts/deepseek-v4.1-flash/00-COMMON.md`.
2. Record the starting branch, `git rev-parse HEAD`, and `git status --short`. Preserve pre-existing changes and identify them separately in the report.
3. Implement only the target's first incomplete milestone, gate, or package. Do not continue to the next one, commit, push, deploy, use production data, or weaken a blocked prerequisite.
4. Inspect the actual code before editing. Reuse existing contracts and migrations. Do not claim success from file presence, static analysis, mocks, or unavailable infrastructure.
5. Run the target's meaningful available checks plus `git diff --check`. Record commands, exit codes, and concise results. Never expose secrets, credentials, tokens, codes, private mail, attachments, raw SMTP/IMAP transcripts, or production logs.
6. Update the required documentation, module handover, root `handover.md`, `ROADMAP.md`, and `CHANGELOG.md` when behavior or status changes.
7. Stop after the one target package and return only the report below. Do not paste whole source files or a full diff.

## Required review report

```text
DEEPSEEK REVIEW PACKET

Target prompt: <path>
Package executed: <name/gate>
Outcome: READY_FOR_CODEX_REVIEW | BLOCKED | FAILED
Starting branch and commit: <branch> <full SHA>
Pre-existing changes: <none, or exact paths not touched>

Implemented:
- <observable behavior, not intentions>

Changed files:
- <path — purpose>

Database/migrations:
- <migration names; new/modified; applied or not applied; evidence>

Configuration/dependencies:
- <names/defaults/dependency changes; no secret values>

Tests and checks:
- `<exact command>` — exit <code> — <concise result>

Safety/failure checks:
- <security, privacy, rollback, concurrency, retry, or recovery evidence relevant to the target>

Git evidence:
- End `git status --short`: <concise output>
- `git diff --stat`: <concise output>
- `git diff --check`: <result>

Unverified/blockers:
- <precise missing evidence and why>

Known issues/integration requests:
- <owner/action required, or none>

Exact next step:
- <one concrete action; do not begin it>

Review notes:
- No commits or pushes were made.
- No secrets or private content are included.
```

End exactly with: **Read and update handover.md before handing over, and require the next contributor to do the same.**

## Launch message

Send DeepSeek only this, replacing the path:

```text
Read and follow prompts/deepseek-v4.1-flash/RUN-ONE-AND-REPORT.md.
TARGET_PROMPT=prompts/deepseek-v4.1-flash/01-RUNTIME-FOUNDATION-AUTH-VERIFY.md
Execute exactly one package, stop, and return the required DEEPSEEK REVIEW PACKET.
```

For outbound gates A-E, use `TARGET_PROMPT=DEEPSEEK-V4.1-FLASH-OUTBOUND-INTEGRATION.md`. Run it again only after Codex reviews the previous packet and repository diff.
