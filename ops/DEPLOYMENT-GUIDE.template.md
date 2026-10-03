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

| Domain | Document root must be | Why |
|---|---|---|
| API domain (e.g. `api.paxofi.com`) | `/paxofi-api-runtime/backend/public` | Only `public/` (index.php, .htaccess) is reachable from the web; `.env`, `src/`, `vendor/` are not. |
| `corporate.paxofi.com` | `/corporate.paxofi.com` (its own folder, normally containing only `cgi-bin`) | It must **not** be the Node.js app folder `paxofi-corporate-website`. If it is, the web server can hand out files from the app folder directly (for example `.env` or `package.json`), and cPanel stores the Node.js routing file (`.htaccess`) inside the app folder, which breaks when the folder is replaced. |

To change a document root: **Domains** → **Manage** next to the domain → **New Document Root** → enter the path above → **Update**.

**HTTPS (required for the contact form).** cPanel → **SSL/TLS Status**: `corporate.paxofi.com`, `www.corporate.paxofi.com` and `api.paxofi.com` must show a valid certificate (if not, select them → **Run AutoSSL**). Then **Domains** → turn on **Force HTTPS Redirect** for `corporate.paxofi.com` and `api.paxofi.com`. The API accepts enquiries only from `https://corporate.paxofi.com`; a page opened over `http://` ("Not secure") cannot send the form.

If you change the document root of `corporate.paxofi.com`, cPanel must re-write its Node.js routing into the new location: do Step 4.4 (**Setup Node.js App → Edit → Save**) as written, and the site will route through Passenger again.

Quick exposure check (before and after): open `https://corporate.paxofi.com/.env` and `https://corporate.paxofi.com/package.json` in a private browser window. Both must show **Not Found** (or the site's 404 page), never the file contents. If `.env` was ever downloadable, treat any secret in it as exposed and change it.

**What the new website package looks like compared to the old folder.** The old `paxofi-corporate-website` folder contained source code (`app/`, `*.ts`, `tsconfig.json`, `package-lock.json`, `node_modules/`, `.next/`, `.env`) because the site was built on the server. This release is built and tested in advance, so the new folder contains only `app.js`, `package.json`, `RELEASE.txt` and `standalone/`. Nothing from the old folder needs to be copied over: the old `.env` is replaced by the `API_BASE_URL` setting in Step 4.4, and `tmp/` and `stderr.log` are recreated by cPanel.

## Step 1 — Back up the database (3 minutes)

cPanel → **phpMyAdmin** → click `paxoalhu_corporate` on the left → **Export** → Method *Quick*, Format *SQL* → **Export**. Keep the downloaded file safe until this release is confirmed working.

(The current code folders are kept automatically: Steps 3 and 4 rename them to `…-old-{{VERSION}}` instead of deleting them.)

## Step 2 — Upgrade the database (5 minutes)

1. phpMyAdmin → click `paxoalhu_corporate` → **Import**.
2. **Choose file** → `database-upgrade-{{VERSION}}.sql`. Leave the other options at their defaults (character set *utf-8*, *Enable foreign key checks* ticked) → **Import**.
3. You should see a green *"Import has been successfully finished"* message.
4. Check: click the database name → the **Structure** list shows every table as **InnoDB** with collation **utf8mb4_unicode_ci**. `products` has **2** rows, `services` **4**, `roles` **2** (Administrator, Business Development), there is a `login_attempts` table, and `schema_migrations` lists the migrations up to the latest one (`007_staff_sign_in_and_roles` or later).

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
   - **Environment variables** → Add Variable: Name `API_BASE_URL`, Value `{{API_URL}}` (type the name without `=`).
   - Variables left over from the old site (`NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_SITE_URL`, `NODE_ENV`) are not used by this package and can be deleted; *Application mode: Production* already sets `NODE_ENV`.
   - **Do not** click *Run NPM Install*: the package already contains everything.
   - **Save** (this writes cPanel's Node.js routing into the domain's document root from Step 0), then **Start App** (or **Restart**).
5. **Test the website:** open {{SITE_URL}}/release.txt: it must show `{{VERSION}}`. Then open {{SITE_URL}} and {{SITE_URL}}/contact. Pages load with styling and no error.

## Step 5 — Final check (5 minutes)

1. On {{SITE_URL}}/contact submit the form with your name, email and a short test message → you see *"Thanks — your enquiry has been received."*
2. phpMyAdmin → `paxoalhu_corporate` → table `enquiries` → **Browse**: your enquiry is there. Table `audit_events`: a row with action `enquiry.submitted` whose `request_id` matches the enquiry's `request_id`.
3. When everything works, delete the now-empty `release-{{VERSION}}` folder. Keep the `…-old-{{VERSION}}` folders and the Step 1 database export for a week, then delete them.

## Step 6 — One time: schedule the data retention clean-up (5 minutes)

From this release the API includes `backend/bin/purge-retention.php`, which deletes enquiries older than 24 months, removes the IP address and browser details from enquiries older than 90 days, deletes audit records older than 24 months (as the privacy page states), and deletes staff sign-in records older than 90 days and ended staff sessions after 30 days. Schedule it once; it then runs every night.

1. cPanel → **Cron Jobs**. Under *Cron Email*, enter the operations email and click **Update Email**.
2. Under *Add New Cron Job*: Common Settings → **Once Per Day**. Change *Minute* to `17` and *Hour* to `3`.
3. Command (one line):

```text
/usr/local/bin/php /home/paxoalhu/paxofi-api-runtime/backend/bin/purge-retention.php >> /home/paxoalhu/logs/purge-retention.log 2>&1
```

4. **Add New Cron Job**. If a scheduled job already exists from an earlier release, skip this step.
5. The next day, open `/home/paxoalhu/logs/purge-retention.log` in File Manager. It shows a line like `… retention purge: 0 enquiries deleted, …`. If it says `Could not open input file` or a PHP version error, edit the cron job and replace `/usr/local/bin/php` with `/opt/cpanel/ea-php84/root/usr/bin/php`.

## Step 7 — One time: create the first staff administrator (10 minutes)

From this release, Paxofi staff read and manage enquiries at **{{SITE_URL}}/admin** instead of phpMyAdmin (decision D-009). The first administrator is created once, with a one-time setup code. Skip this step if anyone can already sign in at `/admin`.

1. **Make a setup code.** Use a password manager, or cPanel → **MySQL Databases** → *Add New User* → **Password Generator** (length **40** or more, letters and numbers). Copy it; do not create the database user.
2. File Manager → `/home/paxoalhu/paxofi-api-runtime/backend/` → right-click `.env` → **Edit** → add one line at the end, then **Save Changes**:

```text
ADMIN_SETUP_TOKEN=paste-the-code-here
```

3. Open {{SITE_URL}}/admin/setup in your browser. Enter the setup code, your name, your work email and a password of at least 12 characters (a short phrase of unrelated words works well) → **Create administrator**. You are signed in and see the enquiries inbox.
4. **Remove the setup code:** edit `.env` again, delete the `ADMIN_SETUP_TOKEN` line, **Save Changes**. (Setup closes by itself once an account exists, but the code should not stay on the server.)
5. **Add the team:** *Users* → **Add a user** → name, email, role (*Business Development* reads and updates enquiries; *Administrator* can also manage users and read the audit log) and a temporary password. Give the password to the person by phone or in person, not by email, and ask them to change it at *My account* after signing in.

Using the staff area:

- **Enquiries** shows new enquiries first. Open one to read it, reply with **Reply by email**, then set its status (*In progress*, *Replied*, *Closed* or *Spam*). Reply within 2 business days (D-007).
- Forgotten password: an administrator opens *Users* → **Edit** → sets a new temporary password. This signs the person out everywhere.
- Someone leaves: *Users* → **Edit** → *Account* → **Disabled**. They are signed out at once and cannot sign in.
- Sessions end after 30 minutes without activity, and after 8 hours at most. After 5 wrong passwords for one email (or 20 from one network) sign-in is blocked for 15 minutes.

## If something goes wrong

| Symptom | Fix |
|---|---|
| API shows *500* or *"The service is misconfigured"* | `.env` missing or wrong in `paxofi-api-runtime/backend/` (Step 3.4); `APP_ENV` must be `production`. Check `paxofi-api-runtime/backend/public/error_log`. |
| `error_log` says *backend/.env exists but is not readable by PHP* | Right-click `.env` → **Change Permissions** → `600` (owner read/write). It must belong to your cPanel account, which it does when created or copied in File Manager. |
| API `/health` gives *404 Not Found* (HTML page) | API domain document root is not `paxofi-api-runtime/backend/public` (Step 0), or `public/.htaccess` is missing (enable *Show Hidden Files*; re-extract if needed) — then redo Step 3.6. |
| `/readiness` shows `"database":false` | `DB_*` values in `.env` are wrong, or the database user lacks privileges on `paxoalhu_corporate` (cPanel → MySQL Databases). |
| Contact form fails and the address bar shows **Not secure** / `http://` (console: *blocked by CORS policy* from origin `http://…`) | The site is being served without HTTPS. Step 0 *HTTPS*: valid certificate (Run AutoSSL) and **Force HTTPS Redirect** on. Do not add `http://` to `CORS_ALLOWED_ORIGINS`. |
| Contact form says *"We could not send your enquiry"*; the browser console (F12) mentions **CORS** | `CORS_ALLOWED_ORIGINS` in the API `.env` must be exactly `{{SITE_URL}}` (no trailing slash). |
| Contact form error without CORS message | `API_BASE_URL` in the Node app's environment variables must be the API base ending in `/api/v1`; restart the app after changing it. |
| Website shows a file list, *403 Forbidden*, downloads a file, or the default cPanel page | The `corporate.paxofi.com` document root is wrong or its Node.js routing was not written: redo Step 0, then Setup Node.js App → **Edit** → **Save** → **Restart**. |
| Website still shows the previous release (`/release.txt` shows the old version, or old pages appear after a restart) | Check `paxofi-corporate-website/RELEASE.txt` in File Manager. If it is old, the new folder was moved *inside* the old one: rename the outer folder to `…-old-{{VERSION}}`, move the inner `paxofi-corporate-website` to `/`, then **Restart**. If `RELEASE.txt` is new but pages are old, the server cache is serving copies of an earlier release: do the one-time cache step below. |
| Website shows *503* / *Incomplete response* | The Node app is stopped or failed: Setup Node.js App → **Start**/**Restart**; check `stderr.log` in `paxofi-corporate-website/`. Make sure the startup file is `app.js`. |
| `/admin/setup` says *Setup is not available* | An account already exists (sign in at `/admin/login`), or `ADMIN_SETUP_TOKEN` is missing or shorter than 32 characters in the API `.env` (Step 7). |
| Staff sign-in says *The admin service could not be reached* | Same causes as a CORS contact-form failure: HTTPS on both domains, `CORS_ALLOWED_ORIGINS` exactly `{{SITE_URL}}`, `API_BASE_URL` set on the Node app. |
| Staff are sent back to *Sign in* straight after signing in | The browser did not keep the session cookie: the API must be opened over `https://` and `APP_ENV` must be `production`. Check that the API domain is a subdomain of the same site (`api.paxofi.com` next to `corporate.paxofi.com`). |
| *Too many attempts* on sign-in | Wait 15 minutes. An administrator cannot lift the block early; if it keeps happening, check the *Audit log* for `staff.sign_in` failures. |
| Database import shows an error | Stop; restore the Step 1 export (phpMyAdmin → Import) and send the error message to engineering. |

### One-time: stop the server cache from serving old pages

Website releases up to and including `20261002-4aa5aa7` told the server cache (LiteSpeed) it could keep pages for up to a year. Later releases forbid caching, but copies already stored can stay until they expire. Without access to LiteSpeed Web Cache Manager, switch the cache off for this site:

1. File Manager (with *Show Hidden Files* on) → `/home/paxoalhu/corporate.paxofi.com/` → right-click `.htaccess` → **Edit**.
2. Add the three lines below at the **very top**, above everything cPanel wrote there. Do not change the `CLOUDLINUX PASSENGER CONFIGURATION` lines.
3. **Save Changes**, then reload the site in a private window.

```apache
<IfModule LiteSpeed>
CacheLookup off
</IfModule>
```

If the site shows an error after saving, remove the three added lines again and contact the hosting provider (Namecheap) to purge the LiteSpeed cache for `corporate.paxofi.com`.

### Roll back to the previous release

1. File Manager: rename the new folders to `…-failed`, and rename the `…-old-{{VERSION}}` folders back to `paxofi-api-runtime` and `paxofi-corporate-website`.
2. Setup Node.js App → **Restart**.
3. Only if the database is the problem: phpMyAdmin → Import the Step 1 export.

The database upgrade is compatible with the previous code, so a code rollback alone does not require a database restore.
