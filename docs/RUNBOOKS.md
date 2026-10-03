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
2. Staff area https://corporate.paxofi.com/admin → **Enquiries**: nothing left in *New* for more than 2 business days (owner: Business Development). Administrators: glance at **Audit log** → *Sign-ins* for repeated failures.
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
2. Address bar shows **Not secure** / the console's CORS message names origin `http://corporate.paxofi.com` → the site is served without HTTPS: RB-6 (certificate) and cPanel → Domains → **Force HTTPS Redirect** on. Never add the `http://` origin to CORS (enquiries would travel unencrypted). (UAT defect DEF-001, 2 Oct 2026.)
3. Other **CORS** message → `CORS_ALLOWED_ORIGINS` in the API `.env` must be exactly `https://corporate.paxofi.com`.
4. Other network error → open `/api/v1/health` and `/readiness`. If the API is down, RB-5.
5. *"You've sent several enquiries…"* → the rate limit (`CONTACT_RATE_LIMIT_MAX` per `CONTACT_RATE_LIMIT_WINDOW_MINUTES`, per email or IP) is working as designed; wait or raise the limit in `.env`.
6. The form posts to `API_BASE_URL` (Setup Node.js App → environment variables); after changing it, **Restart**.

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
2. Rotate secrets: change the database user's password (cPanel → MySQL Databases) and update `DB_PASSWORD` in `.env`; change the cPanel password; make sure `ADMIN_SETUP_TOKEN` is not in `.env`. A leaked `MFA_ENCRYPTION_KEY` matters only together with a copy of the database; if both may be exposed, every staff member resets their two-factor after a new key is set (RB-11). To sign every staff member out at once: phpMyAdmin → SQL → `UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE revoked_at IS NULL;`, then have staff change passwords.
3. Check for unexpected files in the document roots and in `paxofi-api-runtime` (compare with the release ZIP).
4. Redeploy the last known-good release (RB-1) and record the incident in PKDMS.

## RB-9 Dependency and security updates

Dependabot opens weekly update PRs against `develop`. CI fails on any known vulnerability (`composer audit`; frontend `npm run audit`). Merge only green PRs, promote `develop` → `main`, then deploy the new packages (RB-1).

Frontend exceptions: the website's runtime dependencies must always audit clean. An advisory in a development-only tool (linter, test runner) that has no fixed release may be accepted in `frontend/audit-exceptions.json` with the advisory ID, the reason it cannot be exploited here, and an expiry date. CI fails when an entry expires: then update the tool, or renew the entry with a fresh review. Current entry: GHSA-vfj7-8cjw-p6xm (`braces`, via the linter), until 1 Nov 2026 (30-day review).

## RB-10 Data retention clean-up (daily cron job)

Set up once, after the release that ships `backend/bin/purge-retention.php`:

1. cPanel → **Cron Jobs** → *Cron Email*: the operations email (it receives output only when the job prints something).
2. **Add New Cron Job** → Common Settings **Once Per Day** (adjust the minute, e.g. `17 3 * * *`).
3. Command (one line):
   `/usr/local/bin/php /home/paxoalhu/paxofi-api-runtime/backend/bin/purge-retention.php >> /home/paxoalhu/logs/purge-retention.log 2>&1`
   If cPanel shows a different PHP 8.4 path (MultiPHP → *ea-php84*), use `/opt/cpanel/ea-php84/root/usr/bin/php`.
4. Next day: open `logs/purge-retention.log`; each line reads `… retention purge: N enquiries deleted, …`. A line starting `Retention purge failed` → RB-5 (database).

Periods are decision D-008 (enquiries 24 months, IP/user-agent 90 days, audit events 24 months), plus staff sign-in records 90 days and ended staff sessions 30 days (D-009). Recovery codes are deleted when replaced, reset or when two-factor is turned off. To delete one person's enquiry on request: phpMyAdmin → `enquiries` → search by email → Delete (the staff area marks enquiries but does not delete them).

## RB-11 Staff area access (/admin)

Staff sign in at `https://corporate.paxofi.com/admin` (D-009). Roles: **Administrator** (enquiries, users, audit log) and **Business Development** (enquiries).

- **First administrator:** guide Step 7 (one-time `ADMIN_SETUP_TOKEN`; it only works while no accounts exist). Keep **two** administrators so one can always reset the other.
- **The only administrator has lost their password:** engineering generates a password hash for a temporary password and sends the operator one SQL statement (`UPDATE users SET password_hash = '…' WHERE email = '…';`) to run in phpMyAdmin → SQL; the administrator then signs in and changes it at *My account*. Never paste a plain password into the database.
- **Forgotten password:** an administrator → *Users* → **Edit** → new temporary password (signs that person out everywhere).
- **Leaver or lost device:** *Users* → **Edit** → *Account* → **Disabled**. Their sessions end immediately.
- **"Too many attempts":** automatic 15-minute block (5 failures per email, 20 per network). Repeated blocks you cannot explain → RB-8.
- **Sent back to sign in straight away:** cookie not kept: both sites must be on HTTPS and the API `.env` must have `APP_ENV=production` (guide, *If something goes wrong*).
- **Suspected compromised account:** disable it, check **Audit log** for its actions, then RB-8. Disabling, a role change or a password reset revokes all its sessions.
- **Two-factor (D-010):** required for administrators, optional for Business Development.
    - *Lost phone:* sign in with a recovery code; another administrator → *Users* → **Edit** → **Reset two-factor**; set it up again.
    - *Recovery codes running low:* *My account* → *Two-factor sign-in* → **Get new recovery codes** (needs the app).
    - *Only administrator, no phone and no recovery codes:* engineering sends one SQL statement for phpMyAdmin (`UPDATE users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE email = '…';`); the administrator then sets two-factor up again at the next sign-in. Record it as an incident.
    - *`MFA_ENCRYPTION_KEY`:* set once, keep a copy, never rotate casually: changing it breaks every authenticator (recovery codes still work). Never remove it to "skip" two-factor: accounts that have two-factor on still need their code.

## Escalation

| Severity | Examples | Who acts | Target response |
|---|---|---|---|
| **P1 — outage or security** | Website or API down, data exposure, defacement, contact form failing for everyone | Operator on duty → Engineering lead immediately; CEO informed for security incidents | Start within 1 hour; restore service (RB-2 rollback first, investigate after) |
| **P2 — degraded** | Certificate warning, slow pages, intermittent errors, stale content | Operator → Engineering lead same day | Within 1 business day |
| **P3 — minor** | Copy or design fixes, dependency update PRs | Engineering backlog (Asana) | Next planned release |

Hosting-level problems (server down, MariaDB unavailable, LiteSpeed cache purge, DNS) go to the hosting provider (Namecheap support) with the time, the URL and the `request_id` if there is one. Record every P1/P2 in PKDMS with cause, fix and follow-up actions. Service-level targets are decision D-007 (docs/DECISIONS.md).

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
