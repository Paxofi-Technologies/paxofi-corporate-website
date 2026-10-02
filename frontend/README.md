# Frontend

React + Next.js + TypeScript application boundary for the Paxofi Corporate Website.

Implementation follows the canonical frontend application specification and CW task traceability controls.

## Structure

| Path | Purpose |
|---|---|
| `app/` | Routes (App Router). Each page sets its metadata with `pageMetadata()`. |
| `components/` | Shared layout: `SiteHeader` (with the mobile menu) and `SiteFooter`. |
| `lib/site.ts` | Site settings, navigation, public routes, metadata and JSON-LD helpers. |
| `lib/contact.ts` | Contact form API helpers (unit tested). |
| `e2e/` | End-to-end, accessibility and security-header tests. |

## Commands

```bash
npm ci
npm run dev          # development server
npm run typecheck
npm run lint
npm test             # unit tests (node --test)
npm run build
npm run test:e2e     # needs a build; starts `next start` itself
```

`test:e2e` uses Playwright with Chromium (`npx playwright install chromium` once, or set
`PLAYWRIGHT_CHROMIUM_PATH` to an installed Chromium). It checks every public page for one `h1`,
title, description, canonical and Open Graph tags, and **no WCAG 2.1 AA violations** (axe-core);
the 404 page; security headers (CSP, HSTS, nosniff, frame denial); robots and sitemap; skip link;
desktop and mobile navigation; and the contact journey (success, field errors, API unreachable),
with the contact API intercepted in the browser.

## Runtime settings

| Variable | When | Purpose |
|---|---|---|
| `NEXT_PUBLIC_SITE_URL` | build | Canonical URLs, sitemap, robots, structured data. |
| `NEXT_PUBLIC_API_URL` | build | Default API base for the contact form. |
| `API_BASE_URL` | runtime | Overrides the API base without a rebuild (cPanel environment variable). |

Content Security Policy and HSTS are sent by production builds only (`next.config.ts`).
