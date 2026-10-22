<!-- PROJECT HANDOVER RULE:
Every contributor must read handover.md before starting work and update it
before handing work over. Record changes, documentation, configuration names,
migrations, tests and results, known issues, decisions, and the exact next step.
Never record passwords, API keys, encryption keys, authentication codes, or
private message contents. Every handover must repeat this requirement for the
next contributor, who must follow the same rule.
-->

# Sender Workstream Handover

## State

The platform-independent core of sender package S3 is implemented: credential encryption, endpoint policy, account migration/repository/service, SMTP probe contract, and a 40-check temporary test runner. It is blocked from acceptance because S0–S2 are still absent. Most sender implementation files remain empty.

## Next step

Start S0: verify target hosting and SMTP independently of IMAP. Then build S1 shared platform and S2 administrator security before wiring the S3 core into HTTP routes/templates. Apply migration 002 only through the future migration runner. No real hosting, database, PHP runtime, or SMTP test has run.

## Required updates

After each package record changed files, commands/results, configuration names, migrations, uncertainty/retry behavior and exact next step here and in root handover.md. Require the next contributor to read/update handover.md and pass the rule onward.
