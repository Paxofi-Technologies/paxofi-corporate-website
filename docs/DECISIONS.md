# Architecture and product decisions

Short record of decisions that shape the Corporate Website implementation. The canonical register is in the Paxofi PKDMS; this file mirrors decisions that change the code.

## D-001 — Careers and recruitment live on careers.paxofi.com (2 Oct 2026)

**Decision (owner, Paxofi Technologies):** everything concerning careers and recruitment (open roles, applications, CVs, applicant communication, retention) is handled by the careers site at `https://careers.paxofi.com`. The corporate website does not collect job applications.

**Consequences**
- `/careers` on the corporate site describes working at Paxofi and links to `https://careers.paxofi.com` (`SITE.careersUrl` in `frontend/lib/site.ts`); an E2E test asserts the link and that the page has no form.
- Application handling on the corporate API (WBS 013.05–013.10, 013.12: submission service, validation, persistence, status model, security controls, audit events, tests) is out of scope. CV/media upload for applications is not needed here.
- The `career_applications` table from migration 001 is unused and stays empty. It is kept to avoid an unnecessary schema change on production; drop it in a later migration if the careers site never needs it here.
- `GET /api/v1/careers` (published opportunities) remains available but is not used by the website; review it together with the careers site's needs.
- Applicant personal data never reaches the corporate database, so the corporate privacy notice and retention periods (SRS Appendix J) cover enquiries only.

## D-002 — Website follows Paxofi Brand Guidelines v1.0 (2 Oct 2026)

**Decision (owner):** the corporate website is redesigned to the *Paxofi Brand Guidelines v1.0 (2026)* (PKDMS → 01 — Company HQ → Paxofi Brand Guidelines v1.0), replacing the interim sage-green/black styling.

**Implementation**
- Tokens in `frontend/app/globals.css`: Paxofi Blue `#0066FF`, Sky Blue `#00B4FF`, Navy `#0A1F44`, Teal `#10B981`, Purple `#8B5CF6`, Light Gray `#F3F6FA`; Blue → Sky gradient; dot pattern; rounded cards.
- Inter, self-hosted via `@fontsource-variable/inter` (no third-party font requests; CSP `font-src 'self'`).
- Outline icons from `lucide-react`; logo mark and lockup in `components/Logo.tsx` (SVG approximation of the brand mark — replace with the master SVG when the design team supplies it).
- Brand messaging: "Technology for a Brighter Tomorrow", "People | Products | Possibilities", values People First / Innovation Always / Real Solutions / A Brighter Tomorrow, sign-off "A brighter tomorrow, together."
- Accessibility: Sky Blue, Teal and Purple are used only for icons, gradients and text on Navy; links use `#0052CC` so they stay AA on Light Gray. The axe WCAG 2.1 AA scan in E2E covers every page.

## D-003 — The built website is the approved design baseline (2 Oct 2026)

**Decision (owner):** the website as deployed (release `20261002-a157588`, brand redesign per D-002) is approved as the design baseline. No separate Figma design and hand-off is produced for version 1.

**Consequences**
- The Figma design track (CW-DES-001 to CW-DES-015, CW-FE-006 "from approved Figma", CW-QA-011 Figma-to-build QA, CW-DOC-004 Figma hand-off documentation) is closed as superseded. The design system is documented by the code: tokens in `frontend/app/globals.css`, components in `frontend/components/`, and D-002.
- Future design changes start from the live site and the brand guidelines; a designer's Figma work, when it exists, is reconciled against this baseline.

## D-004 — Interim logo until the designer delivers the master artwork (2 Oct 2026)

**Decision (owner):** keep the current SVG logo mark (`frontend/components/Logo.tsx`) until the designer supplies the master logo. Replacing it is a contained change in `Logo.tsx` and `app/icon.svg`.

## D-005 — Version 1 scope: public website only; administration and CMS are Phase 2 (2 Oct 2026)

**Decision (CTO, approved by owner):** version 1 is the public website and its public API (catalog, content, careers listings, contact enquiries). The following are deferred to Phase 2 and are not built:
- staff sign-in, sessions and role-based authorisation (CW-BE-010, CW-BE-011, WBS 017.x, CW-ARCH-007);
- an administration/CMS interface and content revision workflow (CW-BE-006, CW-DES-012, CW-ARCH-008);
- media upload, storage and delivery (WBS 015.x, CW-ARCH-009, CW-CM-005);
- a separate staging environment (CW-OPS-002).

**Why:** the site's content changes rarely and is published through reviewed database migrations (`database/004_seed_published_catalog.sql`), which gives a full history in Git. A sign-in surface would add the largest security risk in the system for little benefit at this size. Enquiries are read in phpMyAdmin (RUNBOOKS.md).

**Consequences:** the unused tables from migration 001 (`users`, `roles`, `permissions`, `sessions`, `content_revisions`, `media_assets`) stay empty and are kept for Phase 2. Changes before production are verified by CI (unit, MariaDB integration, E2E in three browsers, security scan) instead of a staging server.

## D-006 — No analytics or tracking in version 1 (2 Oct 2026)

**Decision (CTO, approved by owner):** the website sets no cookies and loads no analytics, advertising or third-party scripts. Uptime and availability come from UptimeRobot; enquiry volume from the `enquiries` table.

**Why:** no consent banner is needed under the Nigeria Data Protection Act 2023 / GDPR, the Content-Security-Policy allows only this site and the API, and pages stay fast. CW-OPS-007 and CW-ARCH-010 analytics parts are closed by this decision; revisit in Phase 2 with a cookieless, self-hosted option if traffic insight is needed. An E2E test asserts that no page sets a cookie.

*Update 3 Oct 2026: the owner brought analytics into scope; see D-014 (cookieless, self-hosted). Everything else in this decision still holds: no cookies, no third-party scripts.*

## D-007 — Service levels (SRS Appendix K) (2 Oct 2026)

**Decision (CTO, approved by owner), for shared cPanel hosting:**

| Measure | Target |
|---|---|
| Monthly availability, website and API (UptimeRobot, 5-minute checks) | ≥ 99.5% (about 3.6 hours of downtime a month) |
| Page response (Lighthouse, mobile) | LCP ≤ 2.5 s; performance score ≥ 90 |
| API response | p95 ≤ 800 ms for public reads |
| P1 incident (outage, security) | response within 1 hour, restore or roll back within 4 hours |
| P2 incident (degraded) | within 1 business day |
| P3 (minor) | next planned release |
| Enquiry first reply (Business Development) | within 2 business days |
| Recovery point / time | RPO 24 hours (daily cPanel backup + export before each release); RTO 4 hours |

Reviewed in the 30-day operational review (CW-OPS2-004) against actual UptimeRobot data.

## D-008 — Data retention (SRS Appendix J) (2 Oct 2026)

**Decision (CTO, approved by owner):**

| Data | Kept for | Then |
|---|---|---|
| Enquiries (name, email, company, message) | 24 months from receipt | deleted |
| IP address and user-agent on enquiries (abuse protection) | 90 days | cleared |
| Audit events | 24 months | deleted |
| Server logs (PHP error log, Node stderr) | per host rotation, at most 90 days | deleted |
| Database backups | 30 days | deleted |

**Implementation:** `backend/bin/purge-retention.php` applies the first three rows, runs daily from cPanel → Cron Jobs (RUNBOOKS.md RB-10), and records a `data_retention.purged` audit event when it removes anything. Tested against MariaDB in CI (`RetentionPurgeTest`). The privacy page states these periods. Individuals can ask for earlier deletion (hello@paxofi.com).

## D-009 — Phase 2 architecture: staff sign-in and the admin area (3 Oct 2026)

**Decision (CTO, under the owner's standing approval of 2 Oct 2026):** Phase 2 starts with staff sign-in, roles and an enquiries inbox (slice P2.1). The design is chosen for shared cPanel hosting now and moves unchanged to the planned VPS.

- **Where:** the admin area is `https://corporate.paxofi.com/admin` (same Next.js app, never indexed, `Disallow: /admin`). It calls `https://api.paxofi.com/api/v1/admin/*`.
- **Sign-in:** email + password, hashed with PHP `password_hash` (Argon2id when the host supports it, otherwise bcrypt). Passwords are 12–256 characters and must not contain the email address. Wrong email and wrong password give the same message, and unknown accounts still pay the hashing cost.
- **Throttling:** after 5 failed sign-ins for one email or 20 from one IP address within 15 minutes, further attempts get *429* until the window passes. Attempts are kept 90 days.
- **Sessions:** a random 256-bit token in an `HttpOnly`, `Secure`, `SameSite=Strict`, host-only cookie (`__Host-paxofi_admin`) on the API domain. The database stores only its SHA-256 hash (`sessions.id`). Sessions expire after 8 hours, or 30 minutes without activity. Sign-out and password change revoke them.
- **Cross-site protection:** SameSite=Strict, plus every state-changing admin request must carry an `Origin` from `CORS_ALLOWED_ORIGINS`. CORS allows credentials only for those exact origins.
- **Roles (RBAC):**

  | Role | Permissions |
  |---|---|
  | Administrator | `enquiries.read`, `enquiries.update`, `users.manage`, `audit.read` |
  | Business Development | `enquiries.read`, `enquiries.update` |

  Every admin action writes an audit event with the actor's id. A user can't disable or demote their own account.
- **First administrator:** created once through `/admin/setup` with a one-time `ADMIN_SETUP_TOKEN` from the API `.env`. This only works while there are no users; the owner then removes the token. No terminal is needed.
- **Enquiry workflow:** `new` → `in_progress` → `replied` → `closed`, plus `spam`.

**Later slices:**
- P2.2: two-factor sign-in (TOTP) for administrators.
- P2.3: editable products, services and page text (uses `content_items` / `content_revisions`).
- P2.4: media uploads, stored outside the web root.
- P2.5: a staging site on `stage.paxofi.com`.
- P2.6: cookieless self-hosted analytics, after the VPS move.

## D-010 — Two-factor sign-in for staff (3 Oct 2026)

**Decision (CTO, under the owner's standing approval; slice P2.2 of D-009):** staff can add a second sign-in factor: a 6-digit code from an authenticator app (TOTP, RFC 6238). It is **required for administrators** and optional for Business Development.

- **Standard:** TOTP with HMAC-SHA1, 6 digits, 30-second steps, accepting one step either side for clock drift. This is what Google Authenticator, Microsoft Authenticator and 1Password expect. Each step is accepted only once (`users.totp_last_step`), so a code cannot be replayed.
- **Secrets:** generated on the server (160 bits) and shown once as a QR code and a set-up key. They are stored encrypted with AES-256-GCM, using a key derived (HKDF-SHA256) from `MFA_ENCRYPTION_KEY` in the API `.env`. A database copy alone does not reveal them.
- **Recovery codes:** 10 single-use codes of 50 bits each, shown once and stored as SHA-256. They work without the encryption key, so a lost key never locks staff out for good. They can be replaced with a current authenticator code.
- **Sign-in flow:** a correct password opens a **5-minute** session that can only submit the code (`POST /admin/session/mfa`). A valid code replaces it with a new full session and a new token. Code failures count towards the same throttle as passwords (5 per email, 20 per IP address in 15 minutes); at the limit the pending session ends.
- **Enforcement:** once the key is set, an administrator without two-factor can use only *My account*, password change and two-factor set-up until it is on (`MFA_ENROLLMENT_REQUIRED`). Administrators cannot turn it off. Business Development can turn it off with their password.
- **Recovery:** an administrator can reset another person's two-factor; this signs them out everywhere. No one can reset their own. Removing the key never skips the second factor for accounts that have it on.
- **Without the key:** two-factor is unavailable and not enforced. This keeps an upgrade safe before the key is added.
- **Audit:** `staff.sign_in.password_accepted`, `staff.sign_in.second_factor` (failure or denied), `staff.two_factor.enabled`, `.disabled`, `.reset`, `.recovery_codes_replaced`, `.recovery_code_used`.

**Not chosen:** SMS and email codes (interceptable, and they need a paid sender), and WebAuthn/passkeys. Passkeys are stronger but a bigger build; reconsider after the VPS move.

## D-011 — Products and services edited in the staff area (3 Oct 2026)

**Decision (CTO, owner approved option 1 on 3 Oct 2026; slice P2.3):** staff edit the products and services shown on the website from `/admin` → **Content**. Other page text stays in code for now.

- **Content model:** each item has a name, a label (products), an icon from a fixed list of 15, a summary (10–300 characters), up to 5 key points (products), a display order and shown/hidden. Everything is plain text: no markup is accepted or rendered.
- **Workflow:**
  - saving changes only the item's draft;
  - **publishing** copies the draft to the live content and keeps it as a version;
  - any earlier version can be restored as a draft;
  - new items start hidden.
- **Roles:**
  - `content.edit`: Administrator and Business Development; save drafts, add hidden items, restore versions.
  - `content.publish`: Administrator; publish, show or hide.
  - Every change is audited (`catalog.*`).
- **Website:** `/products`, `/services` and the home page's products read published items from the API at request time.
  - On a 1.5-second timeout, an error or an unexpected response, the built-in V1 copy is shown and the reason is logged.
  - The home page's four service *areas* remain page copy.
- **Data:** migration 009 aligns the database with the live wording, touching only rows that still hold the original seed text, so a re-import never overwrites edits. It adds two services so the catalogue matches `/services`.

**Not chosen:**
- All page text editable (option 2): bigger, and long text can break layouts. Revisit with real editing needs.
- A third-party CMS: an extra service to host and secure on shared hosting.

## D-012 — Media library: images and documents (3 Oct 2026)

**Decision (CTO, owner approved on 3 Oct 2026 and asked for PDFs and other documents too; slice P2.4):** staff upload pictures and documents at `/admin` → **Media**. A product or service can show one picture and offer one document (for example a brochure).

- **Accepted files**, judged from the content, not the name:
  - images: JPEG, PNG, WebP, up to 5 MB;
  - documents: PDF, Word (.docx), Excel (.xlsx), PowerPoint (.pptx), plain text and CSV, up to 10 MB.
  - Refused with a clear message: SVG (can carry script), GIF, HEIC, older Office formats and any Office file with macros, programs and archives.
- **Images are re-encoded on upload:** camera rotation applied, all metadata removed (camera, location, comments), longest side at most 2400 px. Each picture needs a description for screen readers.
- **Documents are stored as uploaded** after the type check, and are always downloaded, never opened as a page on the API's address.
- **Public by link:** every file has a fixed address (`/api/v1/media/{id}/{name}`), cached for a year. The library is for public material only; nothing confidential is uploaded.
- **Storage:** files live in one folder outside the website and API release folders (`MEDIA_STORAGE_PATH`, guide Step 10), named by id, so releases never remove them. The database keeps the type, size, description or title, a SHA-256 and who uploaded it (`media_assets`, migration 010).
- **Roles:** `content.edit` (Administrator, Business Development) uploads and edits descriptions and titles; `content.publish` (Administrator) deletes. A file used by a product or service (live or in a draft) cannot be deleted. Every change is audited (`media.*`).
- **Website:** the picture and download link are part of the item's content, so they follow the D-011 draft and publish steps. The page policy allows images from the API's address (CSP `img-src`); media responses allow embedding (`Cross-Origin-Resource-Policy: cross-origin`).
- **Retention:** files stay until staff delete them; they hold no visitor data, so D-008 does not apply.

**Not chosen:**
- Uploading through cPanel only: no link to products and services, and no checks.
- An external file host or CDN: another account and service to secure; revisit with the VPS move if traffic grows.
- Showing PDFs in the browser: inline documents on the API's address are a larger attack surface than downloads.

## D-013 — Staging copy on the same hosting account (3 Oct 2026)

**Decision (CTO, owner said "start" on 3 Oct 2026; slice P2.5):** a private staging copy runs next to the live site on the same cPanel account. Every release is installed and checked there first.

- **Same files as live:** the release package is not rebuilt for staging. The Node app's settings switch it to staging mode, so what is tested is exactly what goes live.
- **Staging mode** (`SITE_ENVIRONMENT=staging`):
  - every page and file needs the staging password (HTTP Basic, `STAGING_PASSWORD`, 12+ characters);
  - it **fails closed**: without a usable password it serves nothing (503);
  - `X-Robots-Tag: noindex, nofollow` on every response, `noindex` in the pages, `robots.txt` disallows everything and the sitemap is empty;
  - a banner on every page says it is the staging site.
- **Separate everything else:** subdomains `staging.corporate.paxofi.com` and `api-staging.paxofi.com`, folders under `/home/paxoalhu/staging/`, database `paxoalhu_corporate_staging`, its own staff accounts, two-factor key and media folder. The API needs no staging code: it runs with `APP_ENV=production` and its own `.env`.
- **Data:** staging starts from `database-install-<version>.sql`, a new file in each release for a new, empty database. The live database is never copied to staging (D-008).
- **Not chosen:**
  - cPanel *Directory Privacy* for the password: it is not covered by tests and can clash with the Node.js routing file;
  - a password on the staging API: it would break the staff area's cross-site calls, and the API holds only test data;
  - a separate staging build: it would no longer test the exact live files;
  - a second hosting account or the VPS: extra cost now. Revisit with the VPS move.

## D-014 — Cookieless, self-hosted visitor analytics (3 Oct 2026)

**Decision (CTO; on 3 Oct 2026 the owner asked for analytics now; slice P2.6; updates D-006):** the website counts its own page views. No third party is involved, nothing is stored on the visitor's device, and no personal data is kept.

- **What is sent:** after each page view, the page path, the screen width and, on the first page of a visit only, the referring site. It is a simple request (`text/plain`, no cookies or credentials) to `POST /api/v1/analytics/pageview`, which always answers 204.
- **What is kept:** daily totals only (`analytics_daily`, `analytics_sources`, `analytics_devices`, migration 011):
  - page views per public page; any other page counts as *(other)*;
  - unique visitors per day;
  - the referring site's domain (for example `google.com`), capped at 500 new domains a day;
  - phone, tablet or desktop.
- **Unique visitors without identification:** SHA-256 of (a random salt for the day + IP address + browser). The salt and the hashes are deleted when the day ends, so a visitor cannot be recognised on another day or traced to an IP address. Totals are kept for 25 months (retention purge).
- **Not counted:** visitors with Do Not Track or Global Privacy Control switched on, known bots and monitors, and the staff area.
- **Who sees it:** the staff area's **Analytics** page (`analytics.read`: Administrator, Business Development). It shows:
  - views, daily visitors and enquiries for 7, 30 or 90 days;
  - top pages, sources and devices.
- **Privacy page:** updated to describe exactly this. No consent banner is needed, because nothing is stored on the device and no personal data is kept.
- **Not chosen:**
  - Google Analytics: uses cookies, needs consent, and sends visitor data to a third party.
  - Plausible or Umami: they need a separate service, a database the hosting does not offer, or a subscription. Revisit after the VPS move if more detail is needed.


## D-015 — Page text edited in the staff area (3 Oct 2026)

**Decision (CTO; on 3 Oct 2026 the owner asked for full page-text editing; slice P2.7; updates the content model in PRODUCT-BASELINE §5):** staff change the wording of the Home, About, Services, Products, Careers and Contact pages under **Content → Page text**, with the same steps as products and services (D-011): save a draft, publish, restore an earlier version.

- **What can be edited:** every heading, paragraph, button label and the search-engine description of those six pages. Each field has a character limit, so the layout cannot break. The fields, their limits and the original wording are listed in one file, `page-copy.json`, kept identical in the website and the API (a test checks this).
- **Menu and footer (added 4 Oct 2026 at the owner's request):** the main menu (up to six links and the button) and the footer (text, headings, email, three links, closing line) are edited the same way under **Content → Page text → Menu and footer**. A link is either a page on this site (`/about`) or an `https://` address; anything else is refused by the API and ignored by the website. Emptying a link's name and address hides it. The footer's Privacy and Terms links stay fixed.
- **What stays in code:** layout, icons and colours, and the **Privacy** and **Terms** pages. Those are legal texts and change only through a reviewed release.
- **Storage:** `page_revisions` (migration 012). A page has at most one draft; every publish is kept as a version.
- **Website:** each page reads `GET /api/v1/pages/{page}` (cached 60 seconds) and shows the published text over the original wording. Any field never published, and the whole page whenever the API cannot be read within 1.5 seconds, shows the original wording, as products and services do.
- **Who:** `content.edit` (Administrator, Business Development) saves drafts; `content.publish` (Administrator) publishes. Every action is in the audit log (`page.*`).
- **Not chosen:**
  - A general page builder: it would let a change break the layout or accessibility.
  - Rich text (bold, links): it adds a sanitising risk for little gain on marketing pages. Revisit if a page needs it.


## D-016 — Email alerts, staff password reset, error alerts and nightly backups (4 Oct 2026)

**Decision (CTO; recommended 3 Oct, the owner asked to start on 4 Oct 2026; slice P2.8):** the API sends email through the company mailbox, and the server keeps its own nightly backups.

- **How email is sent:** through the company mailbox on the hosting account (SMTP, `MAIL_*` settings). Every email is first written to `email_outbox` (migration 013) and sent straight after the page has answered, so a slow mail server never slows the website. A cron job (`bin/send-mail.php`, every 5 minutes) retries anything that failed: after 1, 5, 15, 60 and 240 minutes, then gives up and raises an error alert. Sent emails are deleted after 30 days and failed ones after 90, because they contain personal data. While `MAIL_TRANSPORT` is not set, nothing is sent and the site works as before.
- **New-enquiry alert:** every contact-form enquiry (not spam) emails `ENQUIRY_ALERT_TO`. The email contains the name, email, company, message and a link to the enquiry in the staff area. *Reply* answers the visitor directly (Reply-To).
- **Staff forgot-password:** *Forgot your password?* on the sign-in page.
  - The answer is the same for any address, so the form cannot be used to find out who has an account.
  - The emailed link holds a random 256-bit token, of which only a SHA-256 is stored. It sits after `#`, so it never reaches server logs.
  - The link works once, for 30 minutes.
  - At most 3 links an hour per account and 10 per IP address.
  - A new password signs the person out everywhere and sends them a "password changed" email. Two-factor sign-in still applies at the next sign-in.
  - Everything is audited.
- **Error alerts:** server errors, the database being unreachable, a failed backup and an email given up on all email `ERROR_ALERT_TO`, at most once an hour per problem. These go straight to the mail server, not through the outbox, so they still work when the database is down.
- **Nightly backups:** `bin/backup.php` writes the whole database as a gzipped SQL file (restored with phpMyAdmin → Import) and the media library as a `.tar.gz` to `BACKUP_PATH`, outside the website folders. It keeps 14 days of copies and emails an alert when a backup fails. It is pure PHP, so no `mysqldump` is needed. Backups on the same server do not protect against losing the server, so a copy should be downloaded weekly (RB-18).
- **Not chosen:**
  - A paid email API (SendGrid, Mailgun): one more supplier and contract, when the hosting mailbox is enough for these volumes.
  - Sending inside the request: a slow mail server would slow the contact form.


## D-017 — About and Careers content from the About Us & Career Platform brief (4 Oct 2026)

**Decision (CTO; the owner supplied the brief, the page concepts and screenshots on 4 Oct 2026):** the About and Careers pages carry the brief's sections (Sections 4 and 5). All of their wording is editable under **Content → Page text**.

- **About:**
  - hero, with *Explore careers* and *Join our journey* buttons;
  - who we are, with four facts;
  - our story (four milestones);
  - vision and mission (PKDMS wording);
  - what we do (six services);
  - products and innovation: the products come from **Content → Products**, so nothing is listed that staff have not published;
  - six values;
  - how we work;
  - our people;
  - *Want to build with us?*
- **Careers:**
  - hero;
  - why Paxofi (six reasons);
  - who can join (four audiences);
  - the seven PIF 2026 role families;
  - the Paxofi Innovation Fellowship panel;
  - Learn → Build → Collaborate → Contribute → Grow;
  - the seven recruitment steps;
  - what candidates can expect;
  - nine questions and answers;
  - equal opportunity;
  - a closing call to action with hr@paxofi.com.
  Every application button goes to **careers.paxofi.com**.
- **Wording rules applied:**
  - The brief's content principle: no invented corporate facts. Early-stage is presented honestly.
  - The PKDMS PIF 2026 rules:
    - *Paxofi Innovation Fellow* is the public engagement label;
    - no stipend, allowance or salary is promised;
    - recruitment is rolling, with no closing date or opening counts;
    - terms of 3, 6 or 12 months are confirmed at offer;
    - at least 15 hours a week, 20 hours the target.
  - The screenshots' *Volunteers / Interns / Fellows / Future talent* cards became *who can join* audiences (students and graduates, career changers, early-career and experienced professionals). A note says everyone joins as a PIF Fellow and that any volunteer, internship or employment roles are listed separately. This keeps the screenshots' message without breaking the PIF classification rules.
  - *Are these paid internships?* is answered plainly: no stipend at this time.
- **To validate (ABOUT-00):** the owner checks the facts (*Founded in 2026*, the story milestones) and can change them under **Content → Page text** without a release.

## D-018 — careers.paxofi.com: the careers site and its roles (4 Oct 2026)

**Decision (CTO; the owner asked on 4 Oct 2026 for careers.paxofi.com, not career.paxofi.com, before any campaign goes out):** the careers site is the same website release run a second time, as its own cPanel Node.js app with `SITE_SECTION=careers`.

- **One codebase, two sites.** `proxy.ts` serves the internal `app/careers-site` pages at the careers address and hides them on the corporate site. The careers site has its own header, footer, page titles, canonical address (`CAREERS_SITE_URL`), robots.txt and sitemap (home, each open role, privacy notice). It shows no staff area. Every release updates both apps from the same ZIP.
- **Content.**
  - The fellowship terms, the journey, the seven recruitment steps, what candidates can expect, the FAQ and the equal-opportunity text come from the Careers page text, so one edit under **Content → Page text → Careers** changes both sites.
  - Each role has its own page: purpose, what you will do, deliverables, skills, tools, how it is assessed, and the fixed fellowship terms (remote; at least 15 hours a week, 20 recommended; 3, 6 or 12 months; unpaid).
- **Roles are data.** `career_opportunities` gains a code, family, summary, details (JSON) and display order (migration 014). The seven PIF 2026 roles from the brief are seeded:
  - Project Manager;
  - Program Coordinator;
  - Graphics / Creative Designer;
  - Software Engineer;
  - Frontend Developer;
  - HR Officer;
  - UX/UI Designer.
  Staff with `careers.edit` (Administrator, Human Resources) add, edit, open and close roles under **Recruitment → roles**. A role's address is set once from its title and never changes, so shared campaign links keep working. A closed role is hidden and its applications stay.
- **Not chosen:**
  - A separate careers codebase or a hosted applicant-tracking system: a second product to maintain or a new supplier holding candidates' personal data, for one intake a year.
  - Careers pages under `corporate.paxofi.com/careers/…`: the owner wants a dedicated address for campaigns.

## D-019 — Applications, CVs, the HR role and the recruitment pipeline (4 Oct 2026)

**Decision (CTO; the owner asked on 4 Oct 2026 for CV uploads, PDF or Word up to 5 MB, a portfolio or LinkedIn link, keeping applications for 12 months, and HR staff with their own logins):**

- **The application** asks only for what recruitment needs, following the PIF launch pack's data rules:
  - name, email, optional phone, and country/city;
  - hours a week (15 to 40);
  - why this role, and experience or evidence (50–3,000 characters each);
  - a CV and/or a portfolio or LinkedIn link (at least one);
  - confirmation of being 18 or older;
  - agreement to the applicant privacy notice (version `2026-10-04` is stored).
  Nothing sensitive is asked for (no date of birth, gender, religion, health or finances).
- **CVs:**
  - Uploaded first, as a PDF or Word `.docx` of up to 5 MB, and checked by their content, not their name.
  - Kept in `MEDIA_STORAGE_PATH/applications`, outside the website, where the web server refuses to serve them. They are never public.
  - The upload returns a one-time token (only its SHA-256 is stored) that the application must claim within a day.
  - Staff download a CV through the signed-in API; every download is audited.
- **Abuse limits:**
  - a hidden honeypot field;
  - 10 CV uploads per IP address an hour;
  - 5 applications per IP an hour and 3 per email a day;
  - one open application per person per role.
- **Emails** (through the D-016 outbox, replies to `RECRUITMENT_REPLY_TO`):
  - Every application gets an acknowledgement with its `PIF-XXXXXX` reference.
  - `RECRUITMENT_ALERT_TO` gets an alert with no contact details, linking to the staff area.
  - Staff send the launch pack's candidate emails from templates: screening outcome, assessment invitation, interview invitation, selection, onboarding, not selected and withdrawal. Unreplaced `[placeholders]` are refused, and every email sent is recorded on the application.
- **The HR role and its permissions.** The new role **Human Resources** gets:
  - `recruitment.read`: list, view and download CVs;
  - `recruitment.manage`: stage, scorecards, notes, emails, erase;
  - `careers.edit`: the roles.
  HR staff do not see enquiries, users or the audit log. Administrators get all three permissions. **Two-factor sign-in is required for HR staff**, as for administrators, because they see CVs.
- **The pipeline:** the launch pack's 13 stages, from *Applied* through *Screening*, *Shortlisted*, *Assessment*, *Interview*, *Selected*, *Agreement pending*, *Accepted*, *Onboarding* and *Active*, plus the closing stages *Declined*, *Withdrawn* and *Not selected*. There are two scorecards: Gate 2 evidence review (7 criteria scored 1–5, progression at 21/35) and Gate 4 interview (progression at 24/35). Stage changes, scores, notes and emails form the application's history.
- **Retention (extends D-008):**
  - Applications, their notes and CVs are deleted 12 months after they close, or 12 months after their last stage change while still open.
  - Applicants' IP addresses and user-agents are deleted after 90 days.
  - CV uploads never attached to an application are deleted after one day.
  - The daily retention job (Step 6) does all of this, CV files included.
  - Staff can erase an application at once, for example on request.
  - Backups roll over within 14 days.
- **Not chosen:**
  - Emailing CVs to HR as attachments: copies would sit in mailboxes outside the retention rule.
  - Accepting `.doc`, images or ZIPs: older formats can carry macros, and images are rarely real CVs.

### D-019 addendum — attachments on candidate emails (4 Oct 2026)

**Found in UAT (owner, 4 Oct):** the *Selected* email said the PIF Participant Agreement was attached, but candidate emails could not carry files.

**Change:**
- Staff can attach one PDF or Word `.docx` file (up to 5 MB, checked by content) to any candidate email.
- The *Selected* template cannot be sent without an attachment.
- The file is kept with the queued email (`email_attachments`, migration 015) and deleted with it: 30 days after it is sent, or 90 days after it fails.
- The application's history records the file name, not the file.
- Queued emails now use the application's clock for their send time, so the outbox and the sender always agree on what is due.

## D-020 — Recruitment campaign tracking and report (P3.1, 5 Oct 2026)

**Decision (CTO; Phase 3 started on the owner's go-ahead, 5 Oct 2026):** before the PIF 2026 campaign, recruitment can see which channels and campaign links bring applicants, and whether new applications are reviewed within the 2-working-day target.

- **Channel:** an optional question on the application form: *How did you hear about this role?* There are 11 fixed answers, from LinkedIn to "Somewhere else". Unknown values are dropped.
- **Campaign:** tags on careers links, `utm_source`, `utm_medium` and `utm_campaign`, for example `https://careers.paxofi.com/?utm_source=linkedin&utm_medium=social&utm_campaign=pif-2026`.
  - The careers site remembers them for the browser tab (sessionStorage, no cookie), so an application made a few pages later still records the campaign.
  - Only lower-case letters, digits, `.`, `_` and `-` are kept, up to 80 characters.
- **Review time:** `first_reviewed_at` is set the first time an application leaves *Applied*. The target is 2 working days, Monday to Friday, Lagos time.
- **Report** (staff area → **Recruitment → See the recruitment report**; `recruitment.read`):
  - applications for the last 7, 30 or 90 days, or all;
  - broken down by channel, campaign link, role, current stage and day;
  - the share reviewed on time, the median hours to first review, and how many are waiting or overdue.
  - It shows counts only, with no names or contact details.
- **Data:** migration 016 adds the columns to `job_applications`, so they are deleted with the application (12 months). The applicant privacy notice now names them (version 2026-10-05).
- **Not chosen:** page-view tracking on the careers site. Applications per channel answer the campaign question with less data, and the corporate analytics (D-014) stays as it is.

## D-021 — News & Insights (P3.2, 5 Oct 2026)

**Decision (CTO; SRS chapter 14 lists "blog articles and insights" and "news and announcements"):** the corporate website gets a **News & Insights** section at `/insights`, written in the staff area.

- **Articles:**
  - Each has a title, a category (*News*, *Insight* or *Announcement*), a summary (shown on the list, in search results and when shared), an optional author, an optional picture from the Media library, and the article text.
  - The address (`/insights/<title-words>`) is set from the first title and never changes.
- **Text format:**
  - The text is plain, with a few formatting marks: blank lines between paragraphs, `##` headings, `-` and `1.` lists, `**bold**`, and `[links](https://…)`.
  - The website builds the HTML itself and escapes every word, so no HTML or script from an article can run. Links must be https, http, mailto or a page on the site.
  - Not chosen: a rich-text (WYSIWYG) editor. It needs HTML sanitising, adds a large script to the staff area, and pasted Word formatting breaks the brand styles.
- **Workflow (same as products and services, D-011):**
  - Staff with `content.edit` (Business Development) write and save drafts, with a preview.
  - An Administrator (`content.publish`) publishes, publishes later changes, hides or deletes.
  - Saving never changes what the website shows.
  - Every step is audited (`article.*`).
  - A picture an article uses cannot be deleted from the Media library.
- **Search and sharing:**
  - Each article has its own title, description and canonical address.
  - Shared links show the article's picture (`og:type article`).
  - Each page carries NewsArticle or BlogPosting structured data.
  - The sitemap lists published articles.
  - Analytics counts `/insights` and each article page (D-014).
- **Menu:** *Insights* is the default fifth menu item. A site whose menu text was already published under **Content → Page text → Menu and footer** keeps its own menu; add *Insights* → `/insights` there.
- **Editorial (SRS 14.11):** every article needs a purpose and an accountable owner (RB-20). The first pieces are owner task *[OWNER] Plan first Insights articles*.


## D-022 — Product status labels and Industries pages (P3.3 and P3.5, 6 Oct 2026)

**Decision (CTO; the owner asked on 5 Oct 2026 for industry pages from the PKDMS sector list and for product statuses from the PKDMS product records):**

- **Product status (SRS 14.7):**
  - Each product carries one label, shown on its card: *Planned*, *In development*, *Pilot*, *Beta*, *Available*, *Limited availability*, *Paused* or *Retired*. "No label" is allowed.
  - It is part of the product's content, so it follows the same draft and publish steps (D-011). New products start as *Planned*.
  - Starting values come from PKDMS on 5 Oct 2026 (migration 018), applied only where the status is still empty:
    - **Paxofi Pay:** *Planned*. It is in programme design and non-production engineering preparation.
    - **Paxofi Core Framework:** *Available*. v1.0.0 and v1.1.0 are published, and it runs this website's API.
    - **PaxofiCloud:** *In development*. The master blueprint is approved and engineering Gate 3 is pending. It is added as a product unless staff already created it.
- **Industries (SRS 4.6, 13.8; FR-SVC-005):**
  - A third catalogue collection beside products and services, under **Content → Industries**. Drafts, publishing, versions, hide/show and pictures work as for products and services.
  - Each industry has a name, icon, summary, description (paragraphs, up to 1,500 characters), up to 5 example solutions, and up to 6 related products and services.
  - The website lists them at `/industries`. Each has its own page at `/industries/<name-words>`, whose address never changes.
  - Each page links to its related published services and products, at their cards on /services and /products.
  - Migration 018 seeds the ten target industries of SRS 4.6 with their example solutions: Financial Services, Education, Healthcare, Retail & Commerce, Logistics & Transportation, Government, Agriculture, Manufacturing, Non-Profit Organisations, Startups & SMEs.
  - **Claims (SRS 13.8):** the wording says what Paxofi can build. It names no clients and implies no certifications or regulated status; staff keep it that way (RB-21).
- **Page wording:** the Industries page heading, introduction, section headings and call to action are under **Content → Page text → Industries** (D-015).
- **Menu:** *Industries* is the default sixth menu item. A site whose menu was already published adds *Industries* → `/industries` under **Page text → Menu and footer**.
- **Built-in copy:** if the API cannot be read, the website shows the ten industries and the three products from its built-in copy. A test keeps that copy equal to migration 018.
- **Not chosen:** a separate page builder for sector pages (layout risk), or sector pages written in code (staff could not edit them).

## D-023 — Resources page (P3.4, 6 Oct 2026)

**Decision (CTO; the owner said "build it" on 6 Oct 2026; SRS 14.4 "Resources and knowledge materials"):** the corporate website gets a public **Resources** page at `/resources` listing downloadable documents.

- **Source:** documents already in the media library (D-012). Pictures are not listed.
- **Listing:** an Administrator (`content.publish`) opens the document under **Media → Resources page**, chooses a category and writes a 10–300 character description, then lists it. **Take off the Resources page** removes it again; the category and description are kept for next time. Business Development sees whether a document is listed. Every change is audited (`media.resource_listed` / `media.resource_unlisted`).
- **Categories:** Brochures, Guides, Whitepapers, Policies, Other documents, shown in that order.
- **Page:** groups by category, newest listing first. Each document shows its title, its description and a download link with format and size, e.g. "PDF, 1.2 MB".
  - If nothing is listed, the page says so. If the API cannot be read, the page shows a message and the contact email.
  - The page wording is under **Page text → Resources**. A *Resources* link is fixed in the footer, under Company.
- **Safety:** a listed document counts as in use, so it cannot be deleted until it is taken off. Files stay public by link, as before (D-012), so list only material meant for the public.
- **Data:** migration 019 adds `resource_category`, `resource_summary` and `resource_listed_at` to `media_assets`.
- **Not chosen:** gated downloads behind an email form. That would collect personal data and need consent and retention rules; reconsider when there is a marketing need.

## D-024 — Visitor addresses behind Cloudflare (6 Oct 2026)

**Decision (CTO; the owner chose option 2 on 6 Oct 2026, after moving paxofi.com's DNS to Cloudflare):** the API may sit behind Cloudflare's proxy (orange cloud) and still see each visitor's own address.

- **Why it matters:** behind the proxy every request arrives from a Cloudflare address. Without this change, everyone would share the sign-in limit (20 failures per address in 15 minutes), the enquiry, application and password-reset limits, and the analytics visitor count; enquiries and applications would store Cloudflare's address.
- **Rule:** the API uses the `CF-Connecting-IP` header only when the connection comes from one of Cloudflare's published ranges (https://www.cloudflare.com/ips/). Cloudflare always overwrites that header, so a visitor cannot choose their address. A request sent straight to the server keeps its own address. If the host already restores the address, nothing changes.
- **Ranges** are listed in `backend/src/Http/CloudflareClientIp.php` and reviewed yearly (RB-23).
- **DNS:** `corporate`, `www.corporate`, `careers`, `staging.corporate` and `api-staging` are *DNS only*. Two-level names such as `www.corporate` and `staging.corporate` must stay DNS only: Cloudflare's free certificate covers one level only. `api` may be proxied, with SSL/TLS mode **Full (strict)**.
- **Privacy:** the website privacy page has a *Network protection* section, and the applicant privacy notice (version 2026-10-06) says requests pass through Cloudflare.
- **Not chosen:** trusting `X-Forwarded-For` from anyone (can be forged), or a setting to switch the rule off (nothing to gain: the header is only believed from Cloudflare).

## D-025 — Content freshness and retirement (P3.6, 6 Oct 2026)

**Decision (CTO; the owner said "build P3.6" on 6 Oct 2026; SRS 14.19 content review and 14.24 retirement):** the staff area tracks when each piece of public content should next be checked, and keeps old addresses working when a page is retired or renamed.

- **Reviews (Content → Reviews):**
  - Lists everything live on the website: the text of each page (including the menu and footer), published products, services, industries and articles, and documents on the Resources page.
  - Each item shows its review date and a status: *Overdue*, *Due soon* (within 30 days), *No date* or *Up to date*. Overdue items come first.
  - **Mark reviewed** records who checked it and moves the next date 3, 6 or 12 months on (6 by default). A date and a short note can also be set by hand, from today up to three years ahead.
  - Who: anyone who edits content (Administrator, Business Development). Audited `content_review.*`.
  - Dates are Lagos calendar days.
- **Reminder:** administrators get one email a week while anything is overdue or due within 14 days. It is sent by the existing `bin/send-mail.php` cron job, so there is nothing new to schedule. No email is sent when nothing is due or email is not set up.
- **Redirects (Content → Redirects):**
  - An administrator adds *old address → new address*. The new address is another page on the site or a full `https://` link.
  - The website answers the old address with a permanent redirect (301) and keeps any campaign tags. It reads the list from the API at most once a minute, so a change takes effect within a minute. If the API cannot be read, the last list is kept.
  - Refused: built-in pages (/about, /contact, …), the staff area and API, live industry and article pages, redirects to themselves, and chains (a redirect pointing at another redirect). Up to 500 redirects. Corporate site only.
  - Editors can see redirects; only administrators add or delete them. Audited `redirect.*`.
- **Retiring a page:** hide it (or take it off the Resources page), then add a redirect from its address to the closest replacement (RB-24).
- **Data:** migration 020 adds `content_reviews` and `redirects`. No personal data beyond the staff member who reviewed or added an item.
- **Not chosen:**
  - Retiring content automatically on its review date: a missed review should prompt a person, not take a page down.
  - Redirects in the web server (.htaccess): they would need file access for every change and be lost when moving to the VPS.
