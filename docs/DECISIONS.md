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
