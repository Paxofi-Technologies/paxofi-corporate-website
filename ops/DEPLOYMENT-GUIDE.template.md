# Paxofi Corporate Website — cPanel Upload Deployment Guide

**Release:** `{{VERSION}}`
**Website:** {{SITE_URL}}
**API (default):** {{API_URL}}

This release is deployed by uploading files through **cPanel File Manager** and **phpMyAdmin**. There is no Git, Composer, npm or terminal step.

## What is in this release

| File | Where it goes |
|---|---|
| `database-upgrade-{{VERSION}}.sql` | phpMyAdmin → database `paxoalhu_corporate` → **Import** (and the staging database, Step 11) |
| `database-install-{{VERSION}}.sql` | Only when creating the staging copy: phpMyAdmin → the new, empty staging database → **Import** (Step 11) |
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
4. Check: click the database name → the **Structure** list shows every table as **InnoDB** with collation **utf8mb4_unicode_ci**. `products` has **2** rows, `services` **6**, `roles` **2** (`administrator`, `business_development`), there is a `login_attempts` table, and there are `recovery_codes` and `catalog_revisions` tables, and `schema_migrations` lists the migrations up to the latest one (`009_editable_catalog` or later).

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

## Step 8 — One time: turn on two-factor sign-in (15 minutes)

From this release, staff can protect their sign-in with a 6-digit code from an authenticator app on their phone (decision D-010). It is **required for administrators** and optional for Business Development. It needs one setting on the API, made once.

1. **Make an encryption key** the same way as the setup code in Step 7: a password manager or cPanel's **Password Generator**, **40 or more** letters and numbers (no spaces, `#` or quotes).
2. **Keep a copy of the key** in your password manager. If it is lost or changed, everyone's authenticator stops working: staff then sign in with a recovery code and set up their authenticator again.
3. File Manager → `/home/paxoalhu/paxofi-api-runtime/backend/` → right-click `.env` → **Edit** → add one line at the end → **Save Changes**:

        MFA_ENCRYPTION_KEY=paste-the-key-here

    When you deploy a later release, the `.env` is copied over in Step 3.4, so the key stays. Unlike the setup code, **do not delete this line**.

4. **Set up your own two-factor:** sign in at {{SITE_URL}}/admin. As an administrator you are taken straight to **Two-factor sign-in**:
    - install an authenticator app if you don't have one (Google Authenticator, Microsoft Authenticator or 1Password);
    - **Set up two-factor sign-in** → scan the QR code with the app (or type the set-up key) → enter the 6-digit code the app shows → **Turn on two-factor sign-in**;
    - **save the 10 recovery codes** (copy them into your password manager or download the file). Each works once if you lose your phone. They are not shown again.

5. Sign out and sign in again: after your password, enter the current code from the app.
6. Every other administrator does step 4 at their next sign-in. Business Development staff can turn it on at *My account* → **Set up two-factor sign-in** (recommended).

Lost phone:

- **Business Development:** an administrator opens *Users* → **Edit** → **Reset two-factor**. The person signs in with their password and sets it up again if they wish.
- **Administrator:** sign in with one of your recovery codes, then ask another administrator to reset your two-factor and set it up again. With only one administrator and no recovery codes left, see RUNBOOKS RB-11.

## Step 9 — Editing products and services (no server change)

From this release, the products and services on the website are edited in the staff area under **Content** (decision D-011). There is nothing to set up. The website pages look the same as before, because the database upgrade copies the current wording in.

1. {{SITE_URL}}/admin → **Content** → **Products** or **Services** → click an item.
2. Change the wording. The **Preview** shows how the card will look.
3. **Save draft** keeps the change without touching the website. Business Development staff can only save drafts.
4. An administrator presses **Publish**. The website shows the new wording on the next page load.
5. **Hide from website** / **Show on website** controls whether visitors see the item. New items start hidden.
6. **Earlier versions** → **Restore as draft** brings back any previous wording; publish it to put it live again.

## Step 10 — One time: turn on uploads for pictures and documents (10 minutes)

From this release, staff can upload pictures and documents under **Media** and choose them for a product or service (decision D-012). Files are kept in their own folder, outside the release folders, so later releases never remove them.

1. **Create the folder:** File Manager → `/home/paxoalhu/` → **+ Folder** → name `paxofi-media` → **Create New Folder**. It must sit next to `paxofi-api-runtime`, not inside it and not inside `public_html` or `corporate.paxofi.com`.
2. **Tell the API where it is:** File Manager → `/home/paxoalhu/paxofi-api-runtime/backend/` → right-click `.env` → **Edit** → add one line at the end → **Save Changes**:

        MEDIA_STORAGE_PATH=/home/paxoalhu/paxofi-media

    The `.env` is copied over in Step 3.4 of every release, so this stays. **Do not delete this line.**

3. **Check the upload size limit:** sign in at {{SITE_URL}}/admin → **Media**.
    - If the page says *The server currently accepts files up to … MB*, PHP's limit is below 10 MB. This release sets it to 16 MB itself (file `backend/public/.user.ini`); wait 5 minutes and reload.
    - If the message stays: cPanel → **MultiPHP INI Editor** → **Basic Mode** → choose the API domain (`api.paxofi.com`) → set `post_max_size` to `16M` → **Apply**.

4. **Test:** upload a picture (with a short description) and a PDF. Both appear in the list. **Open** shows the picture; the PDF downloads.
5. **Use them:** **Content** → an item → **Picture** and **Document to download** → **Save draft** → **Publish**. The card on the website shows the picture and a download link.

Good to know:

- Anyone with a file's link can open it. Upload only material meant for the public.
- Pictures are cleaned on upload: turned upright, camera and location details removed, and shrunk to at most 2400 pixels wide.
- Only administrators delete files, and only when no product or service uses them.
- **Back up** the `paxofi-media` folder with the database (Step 1): File Manager → right-click `paxofi-media` → **Compress** → download the ZIP.

## Step 10a — Visitor analytics (no server change)

From this release the website counts its own visits, without cookies (decision D-014). There is nothing to set up: the database upgrade adds the tables. Staff open **Analytics** in the staff area. The figures start from the day of deployment. The daily clean-up (Step 6) removes analytics data older than 25 months.

## Step 10b — Page text editing (no server change)

From this release staff can change the wording of the Home, About, Services, Products, Careers and Contact pages under **Content → Page text** (decision D-015). There is nothing to set up: the database upgrade adds the table. The menu and footer are edited the same way (**Content → Page text → Menu and footer**). Until someone publishes a change, every page keeps its current wording. Try it on staging first.

## Step 10c — One time: turn on email and nightly backups (about 30 minutes)

From this release the API can email staff about every new enquiry, send *Forgot your password?* links, email you when something breaks, and back itself up every night (decision D-016). Nothing changes until you add the settings below.

1. **A mailbox to send from.** In cPanel → **Email Accounts** → **Create**, make `no-reply@paxofi.com` with a long random password, or use an existing mailbox. Then click **Connect Devices** for it and note the *Outgoing Server* (for example `mail.paxofi.com`) and the *SMTP Port* for SSL (usually 465).
2. **A backup folder.** In File Manager, create `/home/paxoalhu/paxofi-backups`. It must be outside `public_html`, `paxofi-api-runtime` and `paxofi-corporate-website`.
3. **Settings.** Open `/home/paxoalhu/paxofi-api-runtime/backend/.env` in File Manager → **Edit** and add these lines, using your own values:

```text
MAIL_TRANSPORT=smtp
MAIL_HOST=mail.paxofi.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@paxofi.com
MAIL_PASSWORD="the mailbox password"
MAIL_FROM_ADDRESS=no-reply@paxofi.com
MAIL_FROM_NAME="Paxofi Technologies"
ENQUIRY_ALERT_TO=hello@paxofi.com
ERROR_ALERT_TO=paxofitechnologies@gmail.com
STAFF_AREA_URL=https://corporate.paxofi.com
BACKUP_PATH=/home/paxoalhu/paxofi-backups
BACKUP_KEEP_DAYS=14
```

   `ENQUIRY_ALERT_TO` and `ERROR_ALERT_TO` can hold several addresses separated by commas.
4. **Two cron jobs** (cPanel → **Cron Jobs**, as in Step 6):
   - *Every 5 minutes* (Common Settings → **Once Per Five Minutes**):

```text
/usr/local/bin/php /home/paxoalhu/paxofi-api-runtime/backend/bin/send-mail.php >> /home/paxoalhu/logs/send-mail.log 2>&1
```

   - *Once per day*, at minute `41` and hour `2`:

```text
/usr/local/bin/php /home/paxoalhu/paxofi-api-runtime/backend/bin/backup.php >> /home/paxoalhu/logs/backup.log 2>&1
```

5. **Test.**
   - Send a message through the website's contact form: the alert should arrive within a minute.
   - On the staff sign-in page, use **Forgot your password?** with your own address and follow the link.
   - The next morning, `paxofi-backups` should hold a `paxofi-database-….sql.gz` file.
6. On **staging**, use the same mailbox but `STAFF_AREA_URL=https://staging.corporate.paxofi.com` and its own backup folder (`/home/paxoalhu/paxofi-backups-staging`). Point `ENQUIRY_ALERT_TO` at yourself, so test enquiries don't reach the team.

If an email does not arrive, `email_outbox` in phpMyAdmin shows the reason (RB-17).

## Step 10d — One time: open careers.paxofi.com and HR staff accounts (about 45 minutes)

From this release, **careers.paxofi.com** lists the open Paxofi Innovation Fellowship roles and takes applications with a CV (PDF or Word, up to 5 MB) and a portfolio or LinkedIn link. Applications are reviewed in the staff area under **Recruitment**, by administrators and by **Human Resources** staff with their own logins (decisions D-018, D-019). Applications and CVs are deleted automatically 12 months after they close.

Do Steps 8 (two-factor), 10 (uploads) and 10c (email) first: HR staff must use two-factor sign-in, CVs are stored in the uploads folder, and candidates get emails.

1. **Mailbox for candidates' replies:** cPanel → **Email Accounts** → **Create** `hr@paxofi.com` (if it does not exist). Candidate emails are sent from the `MAIL_FROM_ADDRESS` mailbox with *Reply-To: hr@paxofi.com*.
2. **Domain:** cPanel → **Domains** → **Create A New Domain** → `careers.paxofi.com`, document root `/careers.paxofi.com` (its own folder; untick *Share document root*). Then **SSL/TLS Status** → select it → **Run AutoSSL**, and turn on **Force HTTPS Redirect** under **Domains**. If the address does not open after 30 minutes, add `careers` in the DNS where `paxofi.com` is managed (same IP address as `corporate.paxofi.com`).
3. **API settings:** File Manager → `/home/paxoalhu/paxofi-api-runtime/backend/.env` → **Edit**:
    - change `CORS_ALLOWED_ORIGINS` so it lists both sites, separated by a comma and no spaces:

            CORS_ALLOWED_ORIGINS=https://corporate.paxofi.com,https://careers.paxofi.com

    - add:

            RECRUITMENT_ALERT_TO=hr@paxofi.com
            RECRUITMENT_REPLY_TO=hr@paxofi.com
            CAREERS_SITE_URL=https://careers.paxofi.com

    `RECRUITMENT_ALERT_TO` is told about each new application (several addresses can be separated by commas). The alert has no contact details; the application is read in the staff area.
4. **The careers website** (the same website package, run a second time):
    - Upload `paxofi-corporate-website-{{VERSION}}.zip` to `/home/paxoalhu/release-{{VERSION}}`, **Extract**, and rename the extracted `paxofi-corporate-website` folder to `paxofi-careers-website`. **Move** it to `/home/paxoalhu/`.
    - cPanel → **Setup Node.js App** → **Create Application**: Node.js **22**, mode **Production**, application root `paxofi-careers-website`, application URL `careers.paxofi.com`, startup file `app.js`.
    - **Environment variables:** `API_BASE_URL` = `https://api.paxofi.com/api/v1`, `SITE_SECTION` = `careers`, `CAREERS_SITE_URL` = `https://careers.paxofi.com`.
    - **Create**, then **Start App**. Do not click *Run NPM Install*.
5. **Check the roles:** open `https://careers.paxofi.com`. The seven fellowship roles show. Open one; the application form is at the bottom. `https://careers.paxofi.com/admin` must show *Not found* (the staff area is only on corporate.paxofi.com).
6. **HR staff accounts:** sign in at {{SITE_URL}}/admin → **Users** → add each HR person with the role **Human Resources**. At their first sign-in they set up two-factor sign-in, then see only **Recruitment** and **My account**.
7. **Test:** apply for a role with your own email and a small PDF CV. You should receive the acknowledgement with a `PIF-…` reference, and `RECRUITMENT_ALERT_TO` the alert. In the staff area → **Recruitment**, open the application, download the CV, move it to *Screening*, then **Erase application**.

Every later release: after updating `paxofi-corporate-website` (Step 4), do the same for `paxofi-careers-website` (extract the same ZIP, rename the folder, swap it in, **Restart** its Node.js app). The roles are edited under **Recruitment → Edit the roles on careers.paxofi.com**, without a release.

## Step 11 — One time: create the staging copy (about 45 minutes)

The staging copy is a private second website where each new release is installed and checked **before** it goes live (decision D-013). It uses the same release files as the live site, its own database, folders and media, and a password, so nothing done there touches the live website.

| | Live | Staging |
|---|---|---|
| Website | `corporate.paxofi.com` | `staging.corporate.paxofi.com` |
| API | `api.paxofi.com` | `api-staging.paxofi.com` |
| Folders in `/home/paxoalhu/` | `paxofi-api-runtime`, `paxofi-corporate-website`, `paxofi-media` | the same three names inside `staging/` |
| Database | `paxoalhu_corporate` | `paxoalhu_corporate_staging` |

1. **Folders:** File Manager → `/home/paxoalhu/` → **+ Folder** `staging`. Inside `staging`, create `paxofi-media`.
2. **Domains:** cPanel → **Domains** → **Create A New Domain**:
    - `api-staging.paxofi.com`, document root `/staging/paxofi-api-runtime/backend/public` (untick *Share document root*);
    - `staging.corporate.paxofi.com`, document root `/staging.corporate.paxofi.com` (its own folder, like the live site).
    - Then **SSL/TLS Status** → select both → **Run AutoSSL**, and turn on **Force HTTPS Redirect** for both under **Domains**. If a new address does not open after 30 minutes, the domain's DNS is managed elsewhere: add the two names there (same IP address as `corporate.paxofi.com`).

3. **Database:** cPanel → **MySQL Databases** → create database `corporate_staging` (shown as `paxoalhu_corporate_staging`), create a new user with a new password, and **Add User To Database** with **ALL PRIVILEGES**. Then phpMyAdmin → click the new database → **Import** → `database-install-{{VERSION}}.sql` → **Import**. Use the *install* file only here, and only once.
4. **API:** upload and extract `paxofi-api-runtime-{{VERSION}}.zip` into `/home/paxoalhu/staging/` (you get `staging/paxofi-api-runtime/`). In `backend/`, copy `.env.example` to `.env`, **Edit** it and set:

        APP_ENV=production
        DB_DATABASE=paxoalhu_corporate_staging
        DB_USERNAME=the staging database user
        DB_PASSWORD="the staging database password"
        CORS_ALLOWED_ORIGINS=https://staging.corporate.paxofi.com
        ADMIN_SETUP_TOKEN=a new 32+ character code (delete after step 7)
        MFA_ENCRYPTION_KEY=a NEW 40+ character key (not the live one)
        MEDIA_STORAGE_PATH=/home/paxoalhu/staging/paxofi-media

    **Change Permissions** → `600`. cPanel → **MultiPHP Manager** → tick `api-staging.paxofi.com` → **PHP 8.4** → **Apply**. Check `https://api-staging.paxofi.com/api/v1/readiness` shows `"database":true`.

5. **Website:** upload and extract `paxofi-corporate-website-{{VERSION}}.zip` into `/home/paxoalhu/staging/`. cPanel → **Setup Node.js App** → **Create Application**:
    - Node.js version **22**, Application mode **Production**;
    - Application root `staging/paxofi-corporate-website`, Application URL `staging.corporate.paxofi.com`, startup file `app.js`;
    - **Environment variables:** `API_BASE_URL` = `https://api-staging.paxofi.com/api/v1`, `SITE_ENVIRONMENT` = `staging`, `STAGING_PASSWORD` = a password of 12 or more characters (keep it in your password manager; share it only with staff who test);
    - **Create**, then **Start App**. Do not click *Run NPM Install*.

6. **Check it is private:** open `https://staging.corporate.paxofi.com` in a private window. The browser asks for a user name and password: enter any user name (for example `paxofi`) and the staging password. A yellow line at the top says *Staging site for testing*. Without the password nothing is shown.
7. **Staff account on staging:** open `https://staging.corporate.paxofi.com/admin/setup` and create an administrator with the staging `ADMIN_SETUP_TOKEN`, as in Step 7. Then delete the `ADMIN_SETUP_TOKEN` line from the staging `.env`. Staging accounts are separate from live accounts.

Staging holds no real enquiries or staff accounts. Do not copy the live database into it: it contains personal data (D-008).

## Release routine from now on: staging first

For every new release:

1. **Staging:** do Steps 2–4 on the staging copy, with these changes: the database is `paxoalhu_corporate_staging`; the folders are inside `/home/paxoalhu/staging/` (so `release-{{VERSION}}` goes there too, and the `.env` is copied from `staging/paxofi-api-runtime/backend/`); the PHP version and Node.js app are the staging ones; addresses start with `staging.` / `api-staging.`. The *upgrade* file is used here as on live. A staging backup (Step 1) is optional.
2. **Test on staging:** the release notes say what to check (the UAT scenarios). Tell engineering about anything wrong; the live site is untouched.
3. **Live:** when staging is right, do Steps 1–5 on the live site as usual, with the same files.

If staging and live ever differ in a setting, the difference must be one of: the database name and user, `CORS_ALLOWED_ORIGINS`, `MFA_ENCRYPTION_KEY`, `MEDIA_STORAGE_PATH`, `API_BASE_URL`, `CAREERS_SITE_URL`, and the two staging variables. Everything else is the same.

The careers site (Step 10d) is updated with every release on live, from the same website ZIP. Staging does not need its own careers site: test the careers pages on staging by giving the staging website a second Node.js app only when a release changes them.

## If something goes wrong

| Symptom | Fix |
|---|---|
| API shows *500* or *"The service is misconfigured"* | `.env` missing or wrong in `paxofi-api-runtime/backend/` (Step 3.4); `APP_ENV` must be `production`. Check `paxofi-api-runtime/backend/public/error_log`. |
| `error_log` says *backend/.env exists but is not readable by PHP* | Right-click `.env` → **Change Permissions** → `600` (owner read/write). It must belong to your cPanel account, which it does when created or copied in File Manager. |
| API `/health` gives *404 Not Found* (HTML page) | API domain document root is not `paxofi-api-runtime/backend/public` (Step 0), or `public/.htaccess` is missing (enable *Show Hidden Files*; re-extract if needed) — then redo Step 3.6. |
| `/readiness` shows `"database":false` | `DB_*` values in `.env` are wrong, or the database user lacks privileges on `paxoalhu_corporate` (cPanel → MySQL Databases). |
| Contact form fails and the address bar shows **Not secure** / `http://` (console: *blocked by CORS policy* from origin `http://…`) | The site is being served without HTTPS. Step 0 *HTTPS*: valid certificate (Run AutoSSL) and **Force HTTPS Redirect** on. Do not add `http://` to `CORS_ALLOWED_ORIGINS`. |
| Contact form says *"We could not send your enquiry"*; the browser console (F12) mentions **CORS** | `CORS_ALLOWED_ORIGINS` in the API `.env` must be exactly `{{SITE_URL}}` (no trailing slash). |
| Careers application says *We could not send your application* and the console mentions **CORS** | `CORS_ALLOWED_ORIGINS` must also list `https://careers.paxofi.com` (Step 10d.3). |
| Careers page says *We could not load the open roles* | The careers Node.js app's `API_BASE_URL` is wrong or the API is down; check `/api/v1/readiness`. |
| CV upload says *CV uploads are not available right now* | `MEDIA_STORAGE_PATH` is missing in the API `.env` (Step 10). The API creates `applications/` inside it by itself. |
| Contact form error without CORS message | `API_BASE_URL` in the Node app's environment variables must be the API base ending in `/api/v1`; restart the app after changing it. |
| Website shows a file list, *403 Forbidden*, downloads a file, or the default cPanel page | The `corporate.paxofi.com` document root is wrong or its Node.js routing was not written: redo Step 0, then Setup Node.js App → **Edit** → **Save** → **Restart**. |
| Website still shows the previous release (`/release.txt` shows the old version, or old pages appear after a restart) | Check `paxofi-corporate-website/RELEASE.txt` in File Manager. If it is old, the new folder was moved *inside* the old one: rename the outer folder to `…-old-{{VERSION}}`, move the inner `paxofi-corporate-website` to `/`, then **Restart**. If `RELEASE.txt` is new but pages are old, the server cache is serving copies of an earlier release: do the one-time cache step below. |
| Website shows *503* / *Incomplete response* | The Node app is stopped or failed: Setup Node.js App → **Start**/**Restart**; check `stderr.log` in `paxofi-corporate-website/`. Make sure the startup file is `app.js`. |
| `/admin/setup` says *Setup is not available* | An account already exists (sign in at `/admin/login`), or `ADMIN_SETUP_TOKEN` is missing or shorter than 32 characters in the API `.env` (Step 7). |
| Staff sign-in says *The admin service could not be reached* | Same causes as a CORS contact-form failure: HTTPS on both domains, `CORS_ALLOWED_ORIGINS` exactly `{{SITE_URL}}`, `API_BASE_URL` set on the Node app. |
| Staff are sent back to *Sign in* straight after signing in | The browser did not keep the session cookie: the API must be opened over `https://` and `APP_ENV` must be `production`. Check that the API domain is a subdomain of the same site (`api.paxofi.com` next to `corporate.paxofi.com`). |
| Two-factor page says *not available yet* | `MFA_ENCRYPTION_KEY` is missing from the API `.env`, or shorter than 32 characters (Step 8). |
| *That code is not valid* although the app shows it | The phone's clock is wrong: turn on automatic date and time on the phone. Each code also works only once, so wait for the next one. |
| Everyone's codes stopped working after a change to `.env` | `MFA_ENCRYPTION_KEY` was changed or removed. Put the original key back. If it is lost: sign in with recovery codes, and administrators reset each other's two-factor (RB-11). |
| Published changes do not appear on the website | The website server reads products and services from the API. If it cannot reach it within 1.5 seconds, it shows the built-in wording, and `paxofi-corporate-website/stderr.log` has a line starting `catalog: showing built-in`. Check that `API_BASE_URL` on the Node app is the full `https://api…/api/v1` address and that `{{API_URL}}/products` opens in a browser, then restart the app. |
| **Media** says *File uploads are not set up on the server yet* | `MEDIA_STORAGE_PATH` is missing from the API `.env`, or the folder does not exist or cannot be written (Step 10). The path must be the full path, e.g. `/home/paxoalhu/paxofi-media`. |
| Upload says *The server did not accept a file this large* | PHP's `post_max_size` is below the file size: Step 10, point 3. |
| Upload says *The server cannot process pictures* | PHP's `gd` extension is off: cPanel → **Select PHP Version** (or MultiPHP Manager) → turn on `gd` for PHP 8.4. Documents still upload. |
| Pictures do not show on the website but open from **Media** | The website's `API_BASE_URL` must be the same API address the pictures come from; restart the Node app after changing it. |
| Staging shows *STAGING_PASSWORD is not set* | The staging Node app needs `STAGING_PASSWORD` with 12 or more characters (Step 11.5); **Save** and **Restart**. |
| Staging keeps asking for the password | Use the `STAGING_PASSWORD` value from the staging Node app; the user name can be anything. Close the private window and try again. |
| Staging pages show the live site's content or enquiries arrive in the live database | The staging Node app's `API_BASE_URL` must be `https://api-staging.paxofi.com/api/v1`, and the staging `.env` must name `paxoalhu_corporate_staging`. |
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

1. File Manager: rename the new folders to `…-failed`, and rename the `…-old-{{VERSION}}` folders back to `paxofi-api-runtime` and `paxofi-corporate-website`. Leave `paxofi-media` as it is.
2. Setup Node.js App → **Restart**.
3. Only if the database is the problem: phpMyAdmin → Import the Step 1 export.

The database upgrade is compatible with the previous code, so a code rollback alone does not require a database restore.
