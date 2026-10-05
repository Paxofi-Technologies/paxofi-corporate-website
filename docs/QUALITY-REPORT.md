# Quality report — security, performance, accessibility

Latest run: 2 Oct 2026 (security section updated with the ZAP baseline and nonce CSP).

## Security

| Check | Result | How it is enforced |
|---|---|---|
| npm dependencies (`npm audit`) | **0 vulnerabilities** (after Next.js 15.5 → 16.3.8, which removed the PostCSS advisories) | CI step *Dependency audit* fails on any moderate or higher advisory |
| Composer dependencies (`composer audit`) | **No advisories** | CI step *Dependency audit* in Backend validation |
| Dependency updates | Weekly npm/Composer and monthly GitHub Actions PRs to `develop` | `.github/dependabot.yml` |
| HTTP security headers (website) | Per-request nonce CSP (`script-src 'self' 'nonce-…' 'strict-dynamic'`, `style-src 'self'`, no `unsafe-inline`; `connect-src` only this site and the API origin; `frame-ancestors 'none'`, `object-src 'none'`), HSTS, nosniff, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy, COOP/CORP/COEP, no `X-Powered-By` | E2E: *security headers are sent*; *no page triggers a CSP violation and every page hydrates* (3 browsers) |
| OWASP ZAP baseline (passive scan of the production build) | **0 failures, 0 unaccepted warnings** after fixes: contact form posts (no personal data in URLs), CSP wildcard and `unsafe-inline` removed, COEP added. Accepted with reasons in `.zap/rules.tsv`: 10049 (no-store is intentional), 10109 (informational), 10202 (no session/cookies to forge); 10031 skipped only on `/insights?category=news` (the fixed page title starts with "News") | CI job *Security scan (OWASP ZAP baseline)*; any new warning fails the build |
| Privacy | No cookies, analytics or third-party requests; retention automated (D-008) | E2E *no cookies are set*; `RetentionPurgeTest` |
| Disclosure contact | `/.well-known/security.txt` (RFC 9116) | E2E fails before its `Expires` date |
| Caching of HTML | Every page `private, no-store` — the host cache cannot serve stale releases | E2E test *pages cannot be kept by a shared cache* |
| API | CORS allowlist, honeypot, rate limit (email or IP), safe JSON errors, request IDs, security headers, fail-closed configuration | PHPUnit unit + MariaDB integration suite in CI |
| Live exposure checks (2 Oct) | `/.env` → 403, `/package.json` → 404 | Deployment guide Step 0 |

Remaining: TLS confirmation on `corporate.paxofi.com` (AutoSSL; UAT entry criterion). There is no authenticated surface to scan in v1 (D-005).

## Performance and quality (Lighthouse 12, mobile emulation, production build)

| Page | Performance | Accessibility | Best practices | SEO | LCP | TBT | CLS |
|---|---|---|---|---|---|---|---|
| `/` | 98 | 100 | 100 | 100 | 2.3 s | 60 ms | 0.003 |
| `/contact` | 99 | 100 | 100* | 100 | 1.7 s | 120 ms | 0.029 |
| `/products` | 97 | 100 | 100* | 100 | 2.4 s | 100 ms | 0 |

\* re-measured after adding the site icon (the earlier 96 was a missing `/favicon.ico`).

Measured locally against `next start`; live figures also depend on the host (LiteSpeed/Passenger) and network. Core Web Vitals targets from SRS Ch.15 (LCP ≤ 2.5 s, CLS ≤ 0.1) are met.

## Accessibility

axe-core WCAG 2.0/2.1 A + AA scan of all 8 public pages runs in CI (E2E): 0 violations. Manual keyboard and screen-reader checks remain open (CW-QA-006).
