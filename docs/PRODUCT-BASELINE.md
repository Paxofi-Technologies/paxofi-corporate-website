# Product and delivery baseline — Version 1

Baseline of what version 1 of the Paxofi corporate website is, how it is governed and how it changes. Approved by the owner on 2 October 2026 (design baseline D-003; CTO recommendations accepted). The canonical copy is in PKDMS → 04 — Product & Technology → Corporate Website.

## 1. Vision, objectives and audiences (CW-PD-001)

**Vision:** the public home of Paxofi Technologies, presenting the company as a credible builder of digital products and infrastructure: *Technology for a Brighter Tomorrow.*

| Objective | Measure |
|---|---|
| Present who Paxofi is, what it builds and offers | All 8 pages live, on-brand (D-002), WCAG 2.1 AA |
| Turn interest into conversations | Contact enquiries received and answered within 2 business days (D-007) |
| Route job seekers to recruitment | Every careers link goes to career.paxofi.com (D-001) |
| Be fast, secure and trustworthy | Lighthouse ≥ 90, LCP ≤ 2.5 s, security headers, ZAP scan clean, 99.5% uptime |

**Audiences:** prospective clients and partners (primary), job seekers (routed to the careers site), investors and press, the Paxofi team.

## 2. Sitemap and information architecture (CW-PD-002)

```text
corporate.paxofi.com
├── /            Home
├── /about       About Paxofi
├── /services    Services
├── /products    Products (Paxofi Pay, Paxofi Core Framework)
├── /careers     Working at Paxofi → career.paxofi.com
├── /contact     Contact form (primary conversion)
├── /privacy     Privacy notice
└── /terms       Terms of use
Utility: /sitemap.xml, /robots.txt, /release.txt, /.well-known/security.txt, 404 page
```

Main navigation: About, Services, Products, Careers, and the **Talk to us** button (Contact). Footer: Explore, Contact (email, contact form, careers site), Company (Privacy, Terms).

## 3. Page inventory and objectives (CW-PD-003)

| Page | Objective | Primary action |
|---|---|---|
| Home | State the brand promise, values, services, products and way of working | Build with Paxofi → Contact |
| About | Explain who Paxofi is, mission, values | Contact |
| Services | Describe what Paxofi does for clients | Start a conversation → Contact |
| Products | Present Paxofi Pay and Paxofi Core Framework | Contact |
| Careers | Describe working at Paxofi | View opportunities → career.paxofi.com |
| Contact | Collect an enquiry | Send enquiry |
| Privacy / Terms | Legal information, retention periods (D-008), no tracking (D-006) | – |

## 4. User journeys and conversion paths (CW-PD-004)

1. **Enquiry (primary):** any page → *Talk to us* / CTA band → Contact → fill name, email, company (optional), message → *Send enquiry* → confirmation. Errors are shown next to the field and focused; rate limit and outage messages are explained. Covered by E2E tests in three browsers.
2. **Job seeker:** Home/nav → Careers → *View opportunities* → career.paxofi.com.
3. **Product discovery:** Home → product card *More about …* → Products → Contact.

## 5. Content model (CW-PD-005)

Version 1 page copy lives in the frontend code (`frontend/app/**/page.tsx`), reviewed through pull requests. The API also exposes a published catalogue for future use: `products`, `services`, `content_items` + `content_revisions`, `career_opportunities`, each with `slug`, `lifecycle_state` (draft/published) and `published_at`, seeded by migration 004. Changes to either go through Git and CI. An editorial CMS is Phase 2 (D-005).

## 6. Careers and enquiry requirements (CW-PD-006)

- **Careers:** D-001. No application form or CV upload on the corporate site.
- **Enquiry:** name (required, ≤ 160), email (required, valid), company (optional, ≤ 255), message (required, ≤ 10 000). Hidden honeypot field. Rate limit: 5 per 10 minutes per email or IP address (configurable). Stored with request reference and an `enquiry.submitted` audit event in one transaction. CORS allows only the website origin. Retention per D-008. Business Development reads enquiries in phpMyAdmin and replies within 2 business days.

## 7. SEO, accessibility and performance requirements (CW-PD-007)

| Area | Requirement | Verified by |
|---|---|---|
| SEO | Unique title and description, canonical URL, Open Graph/Twitter cards, Organization JSON-LD, sitemap.xml, robots.txt, content in server HTML | E2E tests |
| Accessibility | WCAG 2.1 AA, one h1 per page, skip link, keyboard menu, visible focus, form errors linked to fields | axe scan on every page in 3 browsers; keyboard E2E |
| Performance | Lighthouse ≥ 90 mobile, LCP ≤ 2.5 s, no layout shift from fonts (self-hosted Inter) | QUALITY-REPORT.md |
| Responsive | 360 px to 1920 px with no horizontal scroll | E2E viewport tests |
| Security | CSP, HSTS, nosniff, frame-ancestors none, COOP/CORP/COEP, no cookies, dependency audit, ZAP baseline | E2E + CI |

## 8. Requirements baseline and hand-off (CW-PD-008)

Requirements are baselined by this document, the SRS in PKDMS, `docs/ENDPOINT-RTM-V1.md`, `docs/SCHEMA-REVIEW.md` and decisions D-001 to D-008. The design hand-off is replaced by the approved built design (D-003).

## 9. Programme structure and ownership (CW-PM-001, CW-PM-002)

| Role | Who | Responsibilities |
|---|---|---|
| Owner / approver | Paxofi Technologies (CEO) | Scope, design and release approval, UAT sign-off |
| CTO, engineering, QA, PM | Claude (acting, by owner's delegation) | Architecture, implementation, review, merge, promotion, release packages, Asana/PKDMS upkeep |
| Operations | Owner's operator on cPanel | Deployments (guide), monitoring alerts, backups, runbooks |
| Business Development | Paxofi team | Answer enquiries |
| Design | External designer | Master logo (D-004); future design work |

Delivery streams: Product, Design, Backend, Frontend, QA, Operations, Content, UAT/Release, Documentation (Asana sections in the PTMS project).

## 10. Workflow, gates, Definition of Ready and Done (CW-PM-003, CW-PM-006)

**Flow:** feature branch → pull request to `develop` → CI green → merge → promotion PR `develop` → `main` → CI green (builds the cPanel packages) → owner deploys with the guide → `/release.txt` confirms.

**Gates:** Gate A = CI green on `develop` (unit, integration, E2E ×3 browsers, accessibility, security scan, dependency audit). Gate B = promotion to `main` plus deployment smoke test (guide Step 5) and owner acceptance.

**Ready:** clear acceptance criteria, decision references where scope is affected, no unresolved dependency.

**Done:** code merged to `main` with tests, CI green, docs/runbooks updated, Asana task closed with an evidence comment (PR, tests, production check), PKDMS updated for decisions.

## 11. Dashboard, communication and escalation (CW-PM-004, CW-PM-005)

- Dashboard: Asana PTMS project (status by section), GitHub Actions (CI), UptimeRobot (availability).
- Communication: progress and decisions in this session and PKDMS; incidents per `docs/RUNBOOKS.md` → Escalation (P1 within 1 hour).

## 12. Evidence and traceability protocol (CW-PM-007, CW-CHG-004)

Every requirement or change is traceable: **decision (D-nnn) → Asana task (CW-…) → pull request → tests in CI → release version (`/release.txt`) → PKDMS page.** Commit messages and PR descriptions name the task IDs; Asana closing comments link the PR and evidence.

## 13. Change control (CW-CHG-001 to CW-CHG-003)

1. **Intake:** anyone raises a change as an Asana task in PTMS: what, why, who asked, urgency.
2. **Impact assessment:** engineering adds: pages/API/database affected, security and privacy impact, test changes, effort, risk, and whether it changes a decision.
3. **Prioritisation and approval:** P1 (outage/security) immediately; otherwise ordered by business value and risk. Changes to scope, design or data handling need owner approval and a new decision record; small fixes are approved by the CTO in review.
4. **Delivery:** the normal flow (section 10); the closing comment records the evidence (section 12).

## 14. Rebaseline (CW-PM-008)

Earlier CW-001 to CW-075 work is mapped into the streams above in Asana; items superseded by D-001 (applications), D-003 (Figma track) and D-005 (Phase 2) are closed or marked Phase 2 with a reason.
