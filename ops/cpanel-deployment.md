# cPanel Deployment — Version 1

Target:
- Next.js frontend via cPanel Application Manager / Passenger, Node.js 22.
- PHP 8.4 API (PCF v1.1.0 consumed by Composer under `vendor/`).
- MariaDB 10.11+ (production: 11.4).

Layout on the cPanel account (`/home/paxoalhu`):

| Path | Role |
|---|---|
| `repositories/paxofi-corporate-website` | cPanel Git clone. **The only source of deployed code.** Tracks `main`. |
| `paxofi-api-runtime/backend` | Runtime copy of `backend/`. The API domain's document root is `paxofi-api-runtime/backend/public`. Holds `.env` and `vendor/`. |
| `paxofi-api-runtime/database` | Runtime copy of `database/` (used by the migration runner). |
| `paxofi-corporate-website` | Next.js application root registered in Application Manager. Holds `.env.production`, `node_modules/`, `.next/`. |
| `backups/corporate-website` | Database backups written by the deploy script (`chmod 700`). |

Release source:
- Engineering changes flow feature branch → `develop` → `main`. Only `main` is deployed.
- Never upload code by hand into the runtime directories; deploy from the Git clone with `ops/deploy-cpanel.sh`. Hand uploads drift from Git and cannot be traced or rolled back.

## One-time setup

1. **Private PCF access for Composer.** PCF is a private GitHub repository. Give Composer a read-only credential (fine-grained token with *Contents: read* on `paxofi-core-framework`), stored only in Composer's auth store:
   `composer config --global github-oauth.github.com <token>`
   Never put it in `composer.json`, Git, or a public directory.
2. **API environment.** Create `paxofi-api-runtime/backend/.env` from the repo's `.env.example`, `chmod 600`:
   - `APP_ENV=production`
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE=paxoalhu_corporate`, `DB_USERNAME`, `DB_PASSWORD`
   - `CORS_ALLOWED_ORIGINS=https://corporate.paxofi.com` (every live frontend origin, comma-separated; without it browsers block the contact form)
3. **Frontend environment.** Create `paxofi-corporate-website/.env.production`, `chmod 600`:
   - `NEXT_PUBLIC_SITE_URL=https://corporate.paxofi.com`
   - `NEXT_PUBLIC_API_URL=https://<api-domain>/api/v1` (the API **base**; the form posts to `{base}/forms/contact/submit`). Next.js inlines these at **build** time, so the deploy script rebuilds after any change.
4. **Application Manager.** Application root `paxofi-corporate-website`, Node.js 22, startup file `app.js`, domain `corporate.paxofi.com`.
5. **API domain.** Document root `paxofi-api-runtime/backend/public`, PHP 8.4 (MultiPHP Manager), TLS enabled.

## Deploying

The very first time, the clone does not yet contain the deploy script, so update it once by hand (cPanel → Git Version Control → *Manage* → *Pull or Deploy* → *Update from Remote*, or):

```bash
git -C ~/repositories/paxofi-corporate-website pull --ff-only
```

From then on, from cPanel Terminal (or SSH):

```bash
bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh
```

The script, in order:

1. fast-forwards the clone to `origin/main` and re-runs itself from the updated copy;
2. **stages** the API (`composer install --no-dev` from the committed `composer.lock`) in `paxofi-api-runtime/.staging` and the frontend (`npm ci && npm run build`) in `~/.paxofi-frontend-staging` — nothing live changes yet;
3. backs up the database to `~/backups/corporate-website`;
4. applies pending migrations with `bin/migrate.php`;
5. **promotes** the staged API and frontend into the live directories (never overwriting `.env`, `.env.production`, `.htaccess`, `.user.ini`, `php.ini` or logs) and restarts Passenger;
6. smoke-tests health, readiness, the database-backed catalogue, the CORS preflight from the site origin, and the home and contact pages.

It stops at the first failure. A failure in steps 1–3 leaves production exactly as it was.

### First deployment onto the existing production database (once)

The production database was built by hand with migrations 001–003 before they were tracked, and every table is MyISAM + latin1 (30 Sep 2026 dump). Adopt it and apply 004–006 (006 converts it to InnoDB + utf8mb4 with foreign keys):

```bash
bash ~/repositories/paxofi-corporate-website/ops/deploy-cpanel.sh --baseline 003
```

Use `--baseline` exactly once. Later deployments run without it; the runner refuses a second baseline.

Useful options: `--skip-frontend` (API/database only), `--skip-backup` (only after a manual phpMyAdmin export). Paths and binaries can be overridden with `REPO_DIR`, `API_RUNTIME_DIR`, `FRONTEND_APP_DIR`, `NODE_VENV`, `PHP_BIN`, `COMPOSER_BIN`.

### Final manual check

Submit the live contact form, then in phpMyAdmin confirm one new row in `enquiries` and a matching `enquiry.submitted` row in `audit_events` with the same `request_id`.

## Rollback / forward recovery

- **Code:** revert the bad commit on `main` through a PR, then run the deploy script again. (The script only fast-forwards, so it never deploys anything that is not on `main`.)
- **Database:** migrations are forward-only. If a migration fails part-way, restore the backup the script wrote just before migrating, or fix forward with a new migration. Migration 006 is safe to re-run. Restore **one** file (the newest `…-before-<commit>.sql.gz` from the failed run; never pass several files at once):

  ```bash
  gunzip -c ~/backups/corporate-website/<file>.sql.gz | mariadb paxoalhu_corporate
  ```

  The script only keeps verified, complete dumps (each ends with `-- Dump completed`); a failed backup leaves no file behind.

## Operational controls

- TLS must be enabled before public traffic.
- Backups must exist before production data collection; the deploy script writes one per deployment, and cPanel's scheduled backups should also include the database.
- Do not expose `backend/` (other than `public/`), `vendor/`, `database/` or repository metadata through a document root.
- Do not commit `vendor/`, `backend/.env`, `.env.production` or any credential.
- API errors are logged as JSON lines to PHP's error log with the request id; ask for the `X-Request-Id` response header value when someone reports a problem.
