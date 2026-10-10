# Operations handover — Paxofi Corporate Website v1 (CW-DOC-006)

Live since 2 Oct 2026 (v1 UAT signed off by the owner that day, `UAT-PLAN.md`). All planned work is live as of **release 25** (6 Oct 2026). The deployed version is always the one shown at `https://corporate.paxofi.com/release.txt`.

## 1. What is running

| Component | Address | Hosting (cPanel account `paxoalhu`) | Health |
|---|---|---|---|
| Website (Next.js 16, Node 22) | https://corporate.paxofi.com | Setup Node.js App, app root `paxofi-corporate-website`, startup `app.js`, env `API_BASE_URL` | `/release.txt`; UptimeRobot |
| API (PHP 8.4, Paxofi Core Framework 1.1) | https://api.paxofi.com/api/v1 | `paxofi-api-runtime/backend/public` (document root), secrets in `backend/.env` (600) | `/health`, `/readiness`; UptimeRobot |
| Database (MariaDB 11.4) | `paxoalhu_corporate` | phpMyAdmin | `/readiness` → `"database":true` |
| Careers site (same release, `SITE_SECTION=careers`) | https://careers.paxofi.com | Setup Node.js App, app root `paxofi-careers-website`, env `API_BASE_URL`, `SITE_SECTION`, `CAREERS_SITE_URL` (guide Step 10d, D-018) | `/release.txt`; add an UptimeRobot monitor |

## 2. Day-to-day

- **Enquiries:** staff area https://corporate.paxofi.com/admin → **Enquiries** (from the Phase 2.1 release; before it, phpMyAdmin → `enquiries`). Business Development replies within 2 business days (D-007). Staff accounts: RUNBOOKS RB-11.
- **Applications:** staff area → **Recruitment** (administrators and Human Resources staff, D-019). Acknowledge within 2 working days, screen within 2 weeks; data requests and erasure: RB-19. Applications and CVs are deleted automatically 12 months after they close.
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
4. Release package, built from `main` (see `ops/README.md` → *Building and checking a release*):
   - `ops/package-release.sh` makes the zips, upgrade/install SQL and guide;
   - `ops/release-guide-pdf.sh` makes the PDF guide;
   - `ops/smoke-release.sh` checks the database scripts against the previous release and runs both zips.
5. The operator deploys with the PDF guide shipped in each release, then checks `/release.txt`.
6. Rollback: RB-2 (the previous folders are kept as `…-old-<version>` for a week).

Dependabot proposes dependency updates weekly; CI blocks known vulnerabilities (RB-9).

## 4. Where things are documented

| Document | Content |
|---|---|
| `DECISIONS.md` (PKDMS: Decision Register D-001 to D-026) | Every product and technical decision, newest last |
| `PRODUCT-BASELINE.md` | Vision, sitemap, journeys, requirements, roles, workflow, change control |
| `RUNBOOKS.md` | Operations, incidents, escalation, monitoring, secrets |
| `ops/DEPLOYMENT-GUIDE.template.md` | Upload deployment (rendered per release, with PDF) |
| `ENDPOINT-RTM-V1.md`, `SCHEMA-REVIEW.md` | API and database reference |
| `QUALITY-REPORT.md`, `UAT-PLAN.md` | Test evidence and UAT results |
| `STABILISATION-REVIEW.md` | Post-launch review pack (CW-OPS2-003); reused for the 30-day review |

## 5. Secrets and access (locations only)

- API database credentials: `paxofi-api-runtime/backend/.env` on the server; never in Git. To rotate, see RB-8.
- GitHub repository secrets `PCF_COMPOSER_AUTH` (Actions and Dependabot) and `PCF_GITHUB_TOKEN` (Dependabot): a fine-grained token with read-only access to `paxofi-core-framework`. Rotate before it expires (RUNBOOKS → GitHub repository secrets).
- cPanel, Namecheap, UptimeRobot, GitHub organisation, Asana and Notion: owner accounts.

## 6. Known issues and follow-ups

| ID | Item | Plan |
|---|---|---|
| KI-001 | Facebook link previews blocked (HTTP 403) by the shared host's bot protection | Retest with the Facebook Sharing Debugger after the move to the VPS; or ask Namecheap to whitelist `facebookexternalhit` |
| D-004 | Interim logo mark | Replace `components/Logo.tsx`, `app/icon.svg` and `public/og-image.png` when the designer delivers the master logo |
| D-005 | Phase 2: staff sign-in (done: P2.1, D-009), two-factor sign-in (done: P2.2, D-010), products and services editing (done: P2.3, D-011), media library (done: P2.4, D-012; back up `paxofi-media` with the database), staging copy (P2.5, D-013: every release goes to staging first; RB-14), visitor analytics (P2.6, D-014, cookieless; RB-15), page text editing (P2.7, D-015; Content → Page text), email alerts, staff password reset, error alerts and nightly backups (P2.8, D-016; RB-17, RB-18) | Asana tasks labelled "[Phase 2]" |
| D-006 | No analytics in v1 | Revisit in Phase 2 (cookieless, self-hosted) |
| VPS | Planned move from shared hosting | Then re-test Facebook (KI-001); consider Git-based deploys and server-level HTTPS/HSTS |

## 7. Upcoming reviews

- **Stabilisation review (CW-OPS2-003)**, 16 Oct 2026. Pack, checklist and agenda: `STABILISATION-REVIEW.md`. Evidence comes from `ops/stabilisation-check.sh` (live sites, read-only) and `ops/stabilisation-evidence.sql` (phpMyAdmin, read-only, counts only). It covers uptime, enquiries answered, recruitment, cron jobs, email, backups, errors and open Dependabot PRs.
- **30-day operational review (CW-OPS2-004)**, about 1 Nov 2026. Measure against the D-007 targets: ≥ 99.5% availability, LCP ≤ 2.5 s, and P1/P2 incident response.
