# Operations handover — Paxofi Corporate Website v1 (CW-DOC-006)

Release in production: **`20261002-747d5a8`** (check `https://corporate.paxofi.com/release.txt`). UAT signed off by the owner on 2 Oct 2026 (`UAT-PLAN.md`).

## 1. What is running

| Component | Address | Hosting (cPanel account `paxoalhu`) | Health |
|---|---|---|---|
| Website (Next.js 16, Node 22) | https://corporate.paxofi.com | Setup Node.js App, app root `paxofi-corporate-website`, startup `app.js`, env `API_BASE_URL` | `/release.txt`; UptimeRobot |
| API (PHP 8.4, Paxofi Core Framework 1.1) | https://api.paxofi.com/api/v1 | `paxofi-api-runtime/backend/public` (document root), secrets in `backend/.env` (600) | `/health`, `/readiness`; UptimeRobot |
| Database (MariaDB 11.4) | `paxoalhu_corporate` | phpMyAdmin | `/readiness` → `"database":true` |
| Careers | https://career.paxofi.com | separate site (decision D-001) | – |

## 2. Day-to-day

- **Enquiries:** staff area https://corporate.paxofi.com/admin → **Enquiries** (from the Phase 2.1 release; before it, phpMyAdmin → `enquiries`). Business Development replies within 2 business days (D-007). Staff accounts: RUNBOOKS RB-11.
- **Monitoring:** UptimeRobot (5 monitors) emails the operations address. Alerts lead to `RUNBOOKS.md` RB-3/RB-5/RB-6.
- **Weekly check (5 minutes):** `RUNBOOKS.md` → Daily/weekly checks.
- **Retention:** the daily cron job `bin/purge-retention.php` (RB-10, guide Step 6). Confirm `logs/purge-retention.log` gains a line each day.
- **Backups:** phpMyAdmin export before every release; cPanel full backup monthly, stored off the server (RB-7).

## 3. Changing the site

1. Every change starts as an Asana task in PTMS (change control: `PRODUCT-BASELINE.md` §13).
2. Engineering works on a branch, opens a pull request to `develop`, and CI must be green:
   - unit, MariaDB integration and component tests;
   - E2E in Chromium, Firefox and WebKit, including WCAG 2.1 AA;
   - OWASP ZAP baseline;
   - dependency audits.
3. Promotion `develop` → `main`; CI builds the cPanel packages.
4. The operator deploys with the PDF guide shipped in each release, then checks `/release.txt`.
5. Rollback: RB-2 (the previous folders are kept as `…-old-<version>` for a week).

Dependabot proposes dependency updates weekly; CI blocks known vulnerabilities (RB-9).

## 4. Where things are documented

| Document | Content |
|---|---|
| `DECISIONS.md` (PKDMS: Decision Register D-001 to D-008) | Careers, brand, design baseline, logo, v1 scope, analytics, service levels, retention |
| `PRODUCT-BASELINE.md` | Vision, sitemap, journeys, requirements, roles, workflow, change control |
| `RUNBOOKS.md` | Operations, incidents, escalation, monitoring, secrets |
| `ops/DEPLOYMENT-GUIDE.template.md` | Upload deployment (rendered per release, with PDF) |
| `ENDPOINT-RTM-V1.md`, `SCHEMA-REVIEW.md` | API and database reference |
| `QUALITY-REPORT.md`, `UAT-PLAN.md` | Test evidence and UAT results |

## 5. Secrets and access (locations only)

- API database credentials: `paxofi-api-runtime/backend/.env` on the server; never in Git. To rotate, see RB-8.
- GitHub repository secrets `PCF_COMPOSER_AUTH` (Actions and Dependabot) and `PCF_GITHUB_TOKEN` (Dependabot): a fine-grained token with read-only access to `paxofi-core-framework`. Rotate before it expires (RUNBOOKS → GitHub repository secrets).
- cPanel, Namecheap, UptimeRobot, GitHub organisation, Asana and Notion: owner accounts.

## 6. Known issues and follow-ups

| ID | Item | Plan |
|---|---|---|
| KI-001 | Facebook link previews blocked (HTTP 403) by the shared host's bot protection | Retest with the Facebook Sharing Debugger after the move to the VPS; or ask Namecheap to whitelist `facebookexternalhit` |
| D-004 | Interim logo mark | Replace `components/Logo.tsx`, `app/icon.svg` and `public/og-image.png` when the designer delivers the master logo |
| D-005 | Phase 2: staff sign-in (done: P2.1, D-009), two-factor sign-in (done: P2.2, D-010), products and services editing (done: P2.3, D-011), media (P2.4), staging (P2.5) | Asana tasks labelled "[Phase 2]" |
| D-006 | No analytics in v1 | Revisit in Phase 2 (cookieless, self-hosted) |
| VPS | Planned move from shared hosting | Then re-test Facebook (KI-001); consider Git-based deploys and server-level HTTPS/HSTS |

## 7. Upcoming reviews

- **Stabilisation review (CW-OPS2-003)**, about 16 Oct 2026. Check:
  - UptimeRobot uptime;
  - enquiries received and answered;
  - the retention cron log;
  - any errors in `stderr.log` / `error_log`;
  - open Dependabot PRs.
- **30-day operational review (CW-OPS2-004)**, about 1 Nov 2026. Measure against the D-007 targets: ≥ 99.5% availability, LCP ≤ 2.5 s, and P1/P2 incident response.
