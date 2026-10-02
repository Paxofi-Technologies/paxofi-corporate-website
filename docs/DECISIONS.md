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
