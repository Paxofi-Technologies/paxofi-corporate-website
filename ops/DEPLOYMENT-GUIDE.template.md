# Paxofi Corporate Website — cPanel Upload Deployment Guide

**Release:** `{{VERSION}}`
**Website:** {{SITE_URL}}
**API (default):** {{API_URL}}

This release is deployed by uploading files through **cPanel File Manager** and **phpMyAdmin**. There is no Git, Composer, npm or terminal step.

## What is in this release

| File | Where it goes |
|---|---|
| `database-upgrade-{{VERSION}}.sql` | phpMyAdmin → database `paxoalhu_corporate` → **Import** |
| `paxofi-api-runtime-{{VERSION}}.zip` | Extracted into `/home/paxoalhu/release-{{VERSION}}`, then moved to `/home/paxoalhu/paxofi-api-runtime` |
| `paxofi-corporate-website-{{VERSION}}.zip` | Extracted into `/home/paxoalhu/release-{{VERSION}}`, then moved to `/home/paxoalhu/paxofi-corporate-website` |
| `SHA256SUMS` | Optional: integrity checksums of the files above |

Folder layout after deployment:

```text
/home/paxoalhu/
├── paxofi-api-runtime/            ← API (PHP 8.4)
│   ├── backend/
│   │   ├── .env                   ← your secrets (you create it once, keep it between releases)
│   │   ├── .env.example           ← template
│   │   ├── public/                ← API domain document root (index.php, .htaccess)
│   │   ├── src/  config/  bin/
│   │   └── vendor/                ← dependencies incl. Paxofi Core Framework (already installed)
│   └── database/                  ← migration files (for reference / bin/migrate.php)
└── paxofi-corporate-website/      ← Node.js app root (Application Manager)
    ├── app.js                     ← startup file
    ├── package.json               ← do NOT click "Run NPM Install"
    └── standalone/                ← prebuilt website (server + its own dependencies)
```

Order matters: **back up → database → API → website → test.** Allow about 30 minutes. Each ZIP is first extracted into a separate `release-{{VERSION}}` folder; the live folders are only swapped at the end of Steps 3 and 4, so the site keeps running while you prepare, and the previous release stays intact for rollback.

---

## Step 0 — Check where your domains point (one time, 2 minutes)

cPanel → **Domains**:

- The **API domain** (e.g. `api.paxofi.com`) document root must be `paxofi-api-runtime/backend/public`.
- The **website domain** `corporate.paxofi.com` is served by the Node.js app (Application Manager, application root `paxofi-corporate-website`).

If the API domain points anywhere else, click **Manage** next to it and change the document root to `paxofi-api-runtime/backend/public`.

## Step 1 — Back up the database (3 minutes)

cPanel → **phpMyAdmin** → click `paxoalhu_corporate` on the left → **Export** → Method *Quick*, Format *SQL* → **Export**. Keep the downloaded file safe until this release is confirmed working.

(The current code folders are kept automatically: Steps 3 and 4 rename them to `…-old-{{VERSION}}` instead of deleting them.)

## Step 2 — Upgrade the database (5 minutes)

1. phpMyAdmin → click `paxoalhu_corporate` → **Import**.
2. **Choose file** → `database-upgrade-{{VERSION}}.sql`. Leave the other options at their defaults (character set *utf-8*, *Enable foreign key checks* ticked) → **Import**.
3. You should see a green *"Import has been successfully finished"* message.
4. Check: click the database name → the **Structure** list shows every table as **InnoDB** with collation **utf8mb4_unicode_ci**. `products` has **2** rows, `services` **4**, and `schema_migrations` lists the migrations up to the latest one.

The import is safe to run again if it is interrupted.

## Step 3 — Deploy the API (10 minutes)

In File Manager turn on **Settings → Show Hidden Files (dotfiles)** first, so `.env` and `.htaccess` are visible.

1. File Manager → home folder (`/home/paxoalhu`) → **+ Folder** → name it `release-{{VERSION}}`.
2. Open `release-{{VERSION}}` → **Upload** → `paxofi-api-runtime-{{VERSION}}.zip`. Wait for 100%, then go back.
3. Right-click the ZIP → **Extract** → into `/home/paxoalhu/release-{{VERSION}}` → **Extract Files**. You now have `release-{{VERSION}}/paxofi-api-runtime/`. Delete the ZIP.
4. **Settings file (`.env`):**
   - If the current API already has one: open `/home/paxoalhu/paxofi-api-runtime/backend/`, right-click `.env` → **Copy** → destination `/release-{{VERSION}}/paxofi-api-runtime/backend` → **Copy File(s)**.
   - First time only: in `release-{{VERSION}}/paxofi-api-runtime/backend/`, right-click `.env.example` → **Copy** → same folder, name `.env`. Then right-click `.env` → **Edit** and set `DB_USERNAME`, `DB_PASSWORD` (cPanel → MySQL Databases) and `CORS_ALLOWED_ORIGINS={{SITE_URL}}` → **Save Changes**.
   - Right-click `.env` → **Change Permissions** → `600`.
5. **Swap (about one minute of API downtime):**
   - In the home folder, right-click `paxofi-api-runtime` → **Rename** → `paxofi-api-runtime-old-{{VERSION}}`.
   - Open `release-{{VERSION}}`, right-click `paxofi-api-runtime` → **Move** → destination `/` (your home folder) → **Move File(s)**.
6. **PHP version:** cPanel → **MultiPHP Manager** → tick the API domain → choose **PHP 8.4** → **Apply**. Do this even if it already shows 8.4: it re-writes the PHP handler into the new `public/.htaccess`.
7. **Test the API** in your browser:
   - `{{API_URL}}/health` → `"status":"ok"`
   - `{{API_URL}}/readiness` → `"database":true`
   - `{{API_URL}}/products` → *Paxofi Pay* and *Paxofi Core Framework*

## Step 4 — Deploy the website (10 minutes)

1. File Manager → open `release-{{VERSION}}` → **Upload** → `paxofi-corporate-website-{{VERSION}}.zip` → back → right-click it → **Extract** into `/home/paxoalhu/release-{{VERSION}}`. You now have `release-{{VERSION}}/paxofi-corporate-website/`. Delete the ZIP.
2. cPanel → **Setup Node.js App** → find the app for `corporate.paxofi.com` → **Stop App**.
3. **Swap:** in the home folder, rename `paxofi-corporate-website` → `paxofi-corporate-website-old-{{VERSION}}`. Then open `release-{{VERSION}}`, right-click `paxofi-corporate-website` → **Move** → destination `/` → **Move File(s)**.
4. Back in **Setup Node.js App** → **Edit** (pencil) on the app:
   - Node.js version: **22**
   - Application root: `paxofi-corporate-website`
   - Application startup file: `app.js`
   - **Environment variables** → Add Variable: `API_BASE_URL` = `{{API_URL}}`
   - **Do not** click *Run NPM Install*: the package already contains everything.
   - **Save**, then **Start App** (or **Restart**).
5. **Test the website:** open {{SITE_URL}} and {{SITE_URL}}/contact. Pages load with styling and no error.

## Step 5 — Final check (5 minutes)

1. On {{SITE_URL}}/contact submit the form with your name, email and a short test message → you see *"Thanks — your enquiry has been received."*
2. phpMyAdmin → `paxoalhu_corporate` → table `enquiries` → **Browse**: your enquiry is there. Table `audit_events`: a row with action `enquiry.submitted` whose `request_id` matches the enquiry's `request_id`.
3. When everything works, delete the now-empty `release-{{VERSION}}` folder. Keep the `…-old-{{VERSION}}` folders and the Step 1 database export for a week, then delete them.

## If something goes wrong

| Symptom | Fix |
|---|---|
| API shows *500* or *"The service is misconfigured"* | `.env` missing or wrong in `paxofi-api-runtime/backend/` (Step 3.4); `APP_ENV` must be `production`. Check `paxofi-api-runtime/backend/public/error_log`. |
| API `/health` gives *404 Not Found* (HTML page) | API domain document root is not `paxofi-api-runtime/backend/public` (Step 0), or `public/.htaccess` is missing (enable *Show Hidden Files*; re-extract if needed) — then redo Step 3.6. |
| `/readiness` shows `"database":false` | `DB_*` values in `.env` are wrong, or the database user lacks privileges on `paxoalhu_corporate` (cPanel → MySQL Databases). |
| Contact form says *"We could not send your enquiry"*; the browser console (F12) mentions **CORS** | `CORS_ALLOWED_ORIGINS` in the API `.env` must be exactly `{{SITE_URL}}` (no trailing slash). |
| Contact form error without CORS message | `API_BASE_URL` in the Node app's environment variables must be the API base ending in `/api/v1`; restart the app after changing it. |
| Website shows *503* / *Incomplete response* | The Node app is stopped or failed: Setup Node.js App → **Start**/**Restart**; check `stderr.log` in `paxofi-corporate-website/`. Make sure the startup file is `app.js`. |
| Database import shows an error | Stop; restore the Step 1 export (phpMyAdmin → Import) and send the error message to engineering. |

### Roll back to the previous release

1. File Manager: rename the new folders to `…-failed`, and rename the `…-old-{{VERSION}}` folders back to `paxofi-api-runtime` and `paxofi-corporate-website`.
2. Setup Node.js App → **Restart**.
3. Only if the database is the problem: phpMyAdmin → Import the Step 1 export.

The database upgrade is compatible with the previous code, so a code rollback alone does not require a database restore.
