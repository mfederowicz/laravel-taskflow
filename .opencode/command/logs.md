---
description: Tails Docker Compose logs for backend, frontend, or nginx. Optional first argument is the service name; extra arguments are passed to docker compose logs.
---

Tail logs from the TaskFlow Docker Compose stack.

Run:
1. `docker compose --env-file .env -f docker/docker-compose.yml logs -f --tail=50 $ARGUMENTS` where `$ARGUMENTS` is anything the user typed after the command (default: no service).

Then summarize in plain text:
- If a service was named, confirm its recent output was captured.
- Note any errors, exceptions, or `ERROR` lines with the surrounding context.
- Keep the summary short; quote the most relevant lines verbatim.