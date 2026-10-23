# Common execution rules

Read `AGENTS.md`, `handover.md`, and `ROADMAP.md` before editing, then read only the files named by the selected task and their direct dependencies. Work directly in this repository. Do not restate prompts, narrate routine steps, paste whole files, commit, or push.

Implement only the selected milestone or its first incomplete sub-package. Inspect source and migration status; reuse existing services/contracts and preserve unrelated changes. Never invent a second bootstrap, auth system, queue, schema, or API. Do not rewrite applied migrations. Only `public/` is web-accessible. Browser mutations use shared auth and action-specific CSRF. Use prepared statements, private storage, Twig escaping, bounded jobs, and verified TLS.

Never use production data for destructive tests. Never store/log credentials, keys, tokens, codes, private mail, attachments, or raw protocol transcripts. The external API remains only `POST /api/v1/mail/send`; no inbox/status/contact/task/OTP API. Keep the old sender unchanged.

Run meaningful available tests. Distinguish static checks, doubles, disposable-database tests, controlled servers, and real-provider evidence. If a dependency/evidence source is unavailable, continue only safe in-scope work and report the blocker; never fake success.

Update relevant docs, `ROADMAP.md`, `CHANGELOG.md`, module handover, and root `handover.md`. Final response only:

1. Package/result.
2. Changed files.
3. Migration/configuration status.
4. Exact tests/results.
5. Unverified issues.
6. Exact next step.

End exactly with: **Read and update handover.md before handing over, and require the next contributor to do the same.**
