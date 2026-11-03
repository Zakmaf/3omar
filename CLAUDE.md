# CLAUDE.md

@AGENTS.md

`AGENTS.md` is the only normative reference for this repository: the common block from Zakmaf/engineering, then the 3omar rules (architecture, calculation sequence, quality gates, hot files). This file only adds what is specific to Claude Code.

## Model policy

- **Approved Opus: Opus 5.5, exact identifier `claude-opus-5-5`.** Default writer for consequential or ambiguous work: payroll engine and formulas, net-to-gross solver, monthly/annual rules, legal or fiscal interpretation, architecture, UX and accessibility, cross-cutting changes, unclear regressions, final pre-release review.
- **Sonnet**: only narrow, mechanical, easily verified work: translation sync, formatting, labels, bounded documentation, direct tests, small refactors.
- No silent fallback to another model. If the required model is unavailable or rate limited, stop and report it to the owner, who alone can authorize another model on the issue.
- State the exact model and the routing reason in the PR report.
- Model labels are versioned (`model:opus-5.5`, `model:sonnet`), as defined by the common standard.

## Permissions

`.claude/settings.json` denies, among others, pushing to `main`, force-pushing, merging, tagging, releasing, triggering workflows, changing repository settings and global Docker cleanups. Do not work around a denial: report it.
