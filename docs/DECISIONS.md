# Architecture and product decisions

Short record of decisions that shape the Corporate Website implementation. The canonical register is in the Paxofi PKDMS; this file mirrors decisions that change the code.

## D-001 — Careers and recruitment live on career.paxofi.com (2 Oct 2026)

**Decision (owner, Paxofi Technologies):** everything concerning careers and recruitment (open roles, applications, CVs, applicant communication, retention) is handled by the careers site at `https://career.paxofi.com`. The corporate website does not collect job applications.

**Consequences**
- `/careers` on the corporate site describes working at Paxofi and links to `https://career.paxofi.com` (`SITE.careersUrl` in `frontend/lib/site.ts`); an E2E test asserts the link and that the page has no form.
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
