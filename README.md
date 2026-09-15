# Paxofi Corporate Website

Official corporate website application for **Paxofi Technologies LTD**.

## Architecture Boundary

This repository is the independent delivery boundary for the Paxofi corporate website.

- Frontend: React + Next.js + TypeScript
- UI: Tailwind CSS + Bootstrap 5
- Backend: Pure PHP 8+ using Paxofi Core Framework (PCF)
- Persistence: MySQL/MariaDB
- Supporting services: Redis where required
- Infrastructure: Nginx, Docker, GitHub Actions, Git/GitHub, subject to the approved deployment topology

The repository does **not** contain a duplicate implementation of PCF. PCF is consumed through its approved interface/package boundary.

## Repository Structure

```text
frontend/
backend/
database/
docs/
tests/
ops/
```

The structure is intentionally established as the initial implementation boundary. Detailed module decomposition follows the canonical Corporate Website architecture and implementation blueprint.

## Development Controls

- Work is traceable to the Corporate Website Implementation Control Register (`CW-###`).
- Branch convention: `feature/CW-###-short-name`, `fix/CW-###-short-name`, `chore/CW-###-short-name`, `security/CW-###-short-name`, or `docs/CW-###-short-name`.
- Commits use: `<type>(CW-###): concise change description`.
- Pull requests must identify affected RTM requirements, tests, security/privacy impact, migrations, deployment considerations, rollback/forward-recovery considerations, and required human gates.
- Secrets must never be committed.

## Status

Repository bootstrap is in progress under CW-001. Repository existence is not implementation completion and does not authorize production deployment.
