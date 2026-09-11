---
description: Prints the repo status used at the start of a session — branch, recent commits, container status, and latest issues-ledger transitions.
---

Show the current state of the TaskFlow repo. Run these and summarize the output briefly:

1. `git branch --show-current` — the active branch.
2. `git log --oneline -8` — what landed recently.
3. `./bin/run.sh status` — whether the Docker Compose services are up.
4. `tail -30 ~/.config/taskflow/issues.md` — latest ledger status transitions (the ledger is the single source of truth for issues; check the last few dated bullets).

Output, in this order:
- **Branch:** <current branch>
- **Recent commits:** <one line each>
- **Services:** <running / not running, per service>
- **Latest ledger transitions:** <the most recent dated bullets>

Keep it to a short list; do not invent or guess content that the commands did not return.