# Task 12 — safe message viewing

Apply `prompts/deepseek-v4.1-flash/00-COMMON.md`. Prerequisite: IMAP sync accepted. Read roadmap milestone 7, architecture/security/database/configuration/testing docs, synchronized-message schema, and parser/sanitizer placeholders.

Implement authenticated panel-only message viewing:

- Decode MIME trees, transfer encodings, encoded headers, charsets, multipart alternatives, and malformed input within explicit size/depth/part limits. Load missing bodies through bounded jobs without blocking normal pages.
- Prefer safe plain text. Sanitize HTML with the reviewed library, render in a restrictive sandbox, remove scripts/forms/active content, and block remote images/fonts/styles/trackers by default. Never inject mail HTML into the panel DOM.
- Store bodies/attachments only in private bounded cache. Add authenticated attachment download using stable IDs, ownership checks, safe disposition/type/length headers, filename/path validation, and no arbitrary path/URL access.
- Show missing/stale/oversized/malformed states honestly. Viewing must not change remote read flags unless an explicit later action requests it.
- Test XSS, SVG/HTML/script/form/CSS/URL attacks, tracker blocking, charset/encoding edge cases, MIME bombs, traversal, unauthorized downloads, range/size behavior, and cache loss/rebuild with synthetic mail only.

No public read endpoint, remote resource proxy, compose/reply, contacts, or automatic harvesting.
