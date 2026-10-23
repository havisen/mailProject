# Codex review of one DeepSeek worktree package

Review only. Do not edit files, apply fixes, update documentation, commit, push, merge, deploy, or start the next package.

## Required inputs

The launch message must provide:

- `TARGET_PROMPT`: the prompt DeepSeek executed.
- `BASE_SHA`: the starting commit from its review packet.
- The complete `DEEPSEEK REVIEW PACKET`.

## Review procedure

1. Read `AGENTS.md`, `handover.md`, `ROADMAP.md`, `prompts/deepseek-v4.1-flash/00-COMMON.md`, `TARGET_PROMPT`, and the packet.
2. Confirm the branch, `HEAD`, `BASE_SHA`, and worktree state. Inspect every staged, unstaged, and untracked file. Compare against `BASE_SHA`; identify pre-existing changes separately. If the packet's base or file list disagrees with Git, report it.
3. Review the actual implementation and directly related existing contracts. Do not trust the packet, comments, filenames, test names, or documentation without confirming behavior.
4. Check target scope and acceptance criteria, dependency order, migrations, transaction boundaries, concurrency/locking, rollback and failure behavior, authentication/authorization, CSRF, validation, escaping, secrets/privacy, TLS, network budgets, retry/idempotency behavior, and data-loss/duplicate-send risks as applicable.
5. Enforce project boundaries: the old sender is unchanged; only `public/` is web-accessible; the external API remains only `POST /api/v1/mail/send`; no public inbox/status/contact/task/OTP endpoints; uncertain or accepted SMTP recipients are never automatically resent.
6. Check that tests exercise meaningful risks and that documentation, configuration names, migration status, `ROADMAP.md`, `CHANGELOG.md`, and handovers match reality. File presence and static parsing are not runtime evidence.
7. Run safe, relevant available checks and `git diff --check`. Never use production data, reveal secrets/private content, contact real providers, apply migrations outside an explicitly disposable database, or run destructive integration tests without a clearly disposable target.
8. Do not fix findings. Give exact file paths and tight line numbers from the current worktree. Rank only actionable defects:
   - `P0`: catastrophic/security-critical or immediate data loss.
   - `P1`: likely security, duplicate-send, corruption, or core-feature failure.
   - `P2`: material correctness, recovery, compatibility, or missing risk-test issue.
   - `P3`: worthwhile low-risk defect; do not report cosmetic preferences.

## Required output

```text
CODEX DEEPSEEK REVIEW

Target: <prompt and package/gate>
Base: <SHA>
Reviewed scope: <staged/unstaged/untracked paths>
Code verdict: APPROVE | REQUEST_CHANGES | BLOCKED
Milestone evidence: ACCEPTED | BLOCKED

Findings (highest severity first):
- [P1] <short title> — <path:line>
  Impact: <what breaks or becomes unsafe>
  Trigger/evidence: <specific execution path or contradiction>
  Minimal remedy: <bounded correction>

If no findings: "No actionable code findings."

Tests/checks independently run:
- `<exact command>` — exit <code> — <result>

Report-to-Git discrepancies:
- <incorrect/missing DeepSeek claim, or none>

Unverified acceptance evidence:
- <runtime/database/concurrency/provider/HTTPS/deployment evidence still missing, or none>

Scope and documentation assessment:
- <concise result>

Recommended next action:
- <fix findings, obtain missing evidence, or authorize the next package>
```

`APPROVE` requires no P0-P2 findings. `ACCEPTED` additionally requires the target's real acceptance evidence; otherwise use `BLOCKED` even if the code verdict is `APPROVE`. If inspection itself is impossible or the diff cannot be isolated, use `BLOCKED` and explain exactly why. Never approve solely because tests reported by DeepSeek passed.

End exactly with: **Read and update handover.md before handing over, and require the next contributor to do the same.**

## Launch message

After DeepSeek stops, open a Codex agent in the same repository and send:

```text
Read and follow prompts/codex/REVIEW-DEEPSEEK-WORKTREE.md.
TARGET_PROMPT=<repository-relative target prompt>
BASE_SHA=<full SHA from the DeepSeek packet>

DEEPSEEK REVIEW PACKET:
<paste the complete packet here>

Review the actual current worktree. Do not change files. Return only the required review.
```
