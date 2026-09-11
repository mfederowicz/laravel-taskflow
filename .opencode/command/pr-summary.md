---
description: Generates a PR description in Added / Changed / Fixed format from the current branch's diff against main.
---

Analyze the current branch's changes compared to `main` and produce a PR description in this exact format:

## Added
- <short, code-focused line describing each new file / feature>

## Changed
- <short, code-focused line describing each modified existing behavior>

## Fixed
- <short, code-focused line describing each bug that was resolved>

Rules:
- Gather the facts yourself before writing anything: run `git status --short`, `git log --oneline main..HEAD`, `git diff main...HEAD --stat`, and read the relevant parts of `git diff main...HEAD` to classify each change correctly.
- Categorize every change into exactly one of Added / Changed / Fixed; omit a section entirely if it has no entries.
- Do NOT list modified file paths, do NOT include test counts, build/pint results, or other housekeeping/gate info, and do NOT mention usernames or the issues ledger.
- Keep each entry to one short sentence describing what the code does now, not how you implemented it.
- If $ARGUMENTS was provided, append it at the end as an extra `## $ARGUMENTS` section.

Return only the markdown PR description, no preamble.