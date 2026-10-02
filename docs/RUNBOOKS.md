# Operational runbooks — Paxofi Corporate Website

For whoever operates `corporate.paxofi.com` and `api.paxofi.com` on the cPanel account `paxoalhu`. Everything here uses cPanel (File Manager, Setup Node.js App, phpMyAdmin, SSL/TLS Status); no terminal is needed.

## At a glance

| Component | Where | Health check |
|---|---|---|
| Website (Next.js, Node 22) | `/home/paxoalhu/paxofi-corporate-website` (app root), document root `/home/paxoalhu/corporate.paxofi.com` | `https://corporate.paxofi.com/release.txt` shows the deployed release |
| API (PHP 8.4, PCF) | `/home/paxoalhu/paxofi-api-runtime/backend` (document root `…/backend/public`) | `https://api.paxofi.com/api/v1/health` → `"status":"ok"`; `/readiness` → `"database":true` |
| Database (MariaDB 11.4) | `paxoalhu_corporate` | `/readiness`; phpMyAdmin |
| Secrets | `paxofi-api-runtime/backend/.env` (permissions 600) | never in Git, never in a public folder |

Every request to the API returns an `X-Request-Id` header and a `request_id` in the JSON body. Ask anyone reporting a problem for that value: it appears in the PHP error log and in `audit_events.request_id`.

## Daily / weekly checks (5 minutes, weekly)

1. Open `/release.txt`, `/api/v1/health` and `/api/v1/readiness`.
2. phpMyAdmin → `enquiries`: new enquiries since last check are answered (owner: Business Development).
3. cPanel → **SSL/TLS Status**: both domains show a valid certificate (AutoSSL renews automatically; check it did).
4. cPanel → **Disk Usage**: no sudden growth (logs, old release folders).
5. Delete `…-old-<version>` folders and `release-<version>` folders older than one week.

## RB-1 Deploy a release

Follow `DEPLOYMENT-GUIDE.md` shipped with the release (generated from `ops/DEPLOYMENT-GUIDE.template.md`). Website-only releases need Step 4 only. Always finish by checking `/release.txt` in a private window.

## RB-2 Roll back

Website or API code: rename the new folder to `…-failed`, rename `…-old-<version>` back to its original name, then Setup Node.js App → **Restart** (website) — the API needs no restart. Database: only if a migration is the problem, phpMyAdmin → Import the export taken in Step 1. Migrations are additive and compatible with the previous code.

## RB-3 Website down or showing an error

| Symptom | Action |
|---|---|
| *503* / *Incomplete response* | Setup Node.js App → **Restart**. Check `paxofi-corporate-website/stderr.log`. Startup file must be `app.js`, Node 22. |
| Old content after a deployment | `/release.txt` shows the old version → the new folder is probably nested inside the old one (see guide troubleshooting). `/release.txt` is new but pages are old → server cache: guide section *One-time: stop the server cache*. |
| File list, 403 or a downloaded file | Document root of `corporate.paxofi.com` must be `/corporate.paxofi.com`; then Setup Node.js App → **Edit → Save → Restart**. |
| "Not secure" in the browser | RB-6. |

## RB-4 Contact form not working

1. Try the form in a private window and press F12 → Console.
2. **CORS** message → `CORS_ALLOWED_ORIGINS` in the API `.env` must be exactly `https://corporate.paxofi.com`.
3. Other network error → open `/api/v1/health` and `/readiness`. If the API is down, RB-5.
4. *"You've sent several enquiries…"* → the rate limit (`CONTACT_RATE_LIMIT_MAX` per `CONTACT_RATE_LIMIT_WINDOW_MINUTES`, per email or IP) is working as designed; wait or raise the limit in `.env`.
5. The form posts to `API_BASE_URL` (Setup Node.js App → environment variables); after changing it, **Restart**.

## RB-5 API down or `"database":false`

| Symptom | Action |
|---|---|
| *500* / *The service is misconfigured* | `.env` missing, unreadable or invalid in `paxofi-api-runtime/backend/` (permissions 600, owned by the account). Check `backend/public/error_log`. |
| `/readiness` `"database":false` | Database credentials in `.env`, or the user lost privileges (cPanel → MySQL Databases → user on `paxoalhu_corporate` with ALL PRIVILEGES). MariaDB status: cPanel → Server Information / host status page. |
| *404* HTML page on `/api/v1/health` | API document root must be `paxofi-api-runtime/backend/public`; `public/.htaccess` present; MultiPHP Manager → PHP 8.4 → Apply. |

## RB-6 TLS certificate problem

cPanel → **SSL/TLS Status** → select `corporate.paxofi.com`, `www.corporate.paxofi.com`, `api.paxofi.com` → **Run AutoSSL**. If AutoSSL reports a DNS error, the domain's A record must point to this server's IP. The website sends HSTS, so browsers refuse plain HTTP once a valid certificate has been seen — fix certificates quickly.

## RB-7 Backups and restore

- **Before every release:** phpMyAdmin → Export (Quick, SQL) — guide Step 1.
- **Regular:** cPanel → Backup (or JetBackup if offered) must include the database; download a full account backup monthly and store it off the server.
- **Restore a database export:** phpMyAdmin → `paxoalhu_corporate` → Import → the `.sql` file. Restore into a new empty database first when you only need to inspect old data.
- Restoring old code is RB-2.

## RB-8 Suspected security incident

1. Preserve evidence: download `backend/public/error_log`, `stderr.log` and a phpMyAdmin export of `audit_events` before changing anything.
2. Rotate secrets: change the database user's password (cPanel → MySQL Databases) and update `DB_PASSWORD` in `.env`; change the cPanel password.
3. Check for unexpected files in the document roots and in `paxofi-api-runtime` (compare with the release ZIP).
4. Redeploy the last known-good release (RB-1) and record the incident in PKDMS.

## RB-9 Dependency and security updates

Dependabot opens weekly update PRs against `develop`. CI fails on any known vulnerability (`npm audit`, `composer audit`). Merge only green PRs, promote `develop` → `main`, then deploy the new packages (RB-1).

## Escalation

| Severity | Examples | Who acts | Target response |
|---|---|---|---|
| **P1 — outage or security** | Website or API down, data exposure, defacement, contact form failing for everyone | Operator on duty → Engineering lead immediately; CEO informed for security incidents | Start within 1 hour; restore service (RB-2 rollback first, investigate after) |
| **P2 — degraded** | Certificate warning, slow pages, intermittent errors, stale content | Operator → Engineering lead same day | Within 1 business day |
| **P3 — minor** | Copy or design fixes, dependency update PRs | Engineering backlog (Asana) | Next planned release |

Hosting-level problems (server down, MariaDB unavailable, LiteSpeed cache purge, DNS) go to the hosting provider (Namecheap support) with the time, the URL and the `request_id` if there is one. Record every P1/P2 in PKDMS with cause, fix and follow-up actions. Final SLA targets are pending SRS Appendix K (SRS-PL-02).

## Monitoring (UptimeRobot)

Owner decision 2 Oct 2026: UptimeRobot (free plan, 5-minute checks, email alerts). Set up once at uptimerobot.com → **Add New Monitor**:

| # | Monitor type | Friendly name | URL | Setting |
|---|---|---|---|---|
| 1 | HTTP(s) | Paxofi website | `https://corporate.paxofi.com/` | Interval 5 min |
| 2 | HTTP(s) | Paxofi website release | `https://corporate.paxofi.com/release.txt` | Interval 5 min (a 404 here means the Node.js app is not serving) |
| 3 | Keyword | Paxofi API readiness | `https://api.paxofi.com/api/v1/readiness` | Keyword `"database":true` → alert when **not exists** |
| 4 | HTTP(s) | Paxofi API health | `https://api.paxofi.com/api/v1/health` | Interval 5 min |

Alert contact: the operations email (verify it in **My Settings → Alert Contacts**) on all four monitors. An alert starts the matching runbook: website monitors → RB-3, API monitors → RB-5, any certificate error → RB-6. Review the 30-day uptime report monthly (CW-OPS2-002/004). The readiness check touches the database once per 5 minutes, which is negligible.

## GitHub repository secrets

| Where (GitHub → Settings → Secrets and variables) | Name | Value | Used by |
|---|---|---|---|
| **Actions** | `PCF_COMPOSER_AUTH` | `{"github-oauth":{"github.com":"<token>"}}` | CI on normal pushes and PRs (installs PCF) |
| **Dependabot** | `PCF_COMPOSER_AUTH` | same JSON as above | CI on Dependabot's update PRs |
| **Dependabot** | `PCF_GITHUB_TOKEN` | `<token>` on its own (no JSON) | Dependabot reading PCF to propose Composer updates |

`<token>` is a GitHub fine-grained personal access token with **read-only Contents** access to `Paxofi-Technologies/paxofi-core-framework` only. Rotate it before it expires and update all three secrets.
