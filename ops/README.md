# Operations

Operational configuration, deployment documentation, runbooks and recovery procedures belong in this boundary.

Secrets, credentials and production-only values must never be committed to the repository.

## Building and checking a release

Run from a clean checkout of `main` (for example `git checkout --detach origin/main`). Number release folders in order (`dist/release25`, `dist/release26`, …) and keep the previous one, because the smoke test upgrades from it.

```bash
# 1. Package: API and website zips, database upgrade + install SQL, guide (Markdown), SHA256SUMS
OUT_DIR=$PWD/dist/release26 bash ops/package-release.sh
#    (add COMPOSER_FLAGS=--ignore-platform-req=php when building with PHP older than 8.4)

# 2. PDF guide: dist/release26/DEPLOYMENT-GUIDE-<version>.pdf, added to SHA256SUMS
#    needs: pip install markdown; npm ci in frontend/
#    (set PLAYWRIGHT_CHROMIUM_PATH if Playwright's own Chromium is not installed)
bash ops/release-guide-pdf.sh dist/release26

# 3. Smoke test against the previous release (local MariaDB; test databases only)
MYSQL_PWD=<local root password> bash ops/smoke-release.sh dist/release26 dist/release25
```

The smoke test checks that:

- the upgrade SQL applies twice on top of the previous release and ends with the same columns and foreign keys as a fresh install;
- both zips start;
- the main pages answer 200;
- the staff API refuses requests without sign-in;
- a redirect answers 301.

Send the folder's six files (two zips, two SQL files, PDF, SHA256SUMS) only when all three steps pass. The `dist/` folder is never committed.

## Checking the live sites

For the weekly check and the stabilisation and 30-day reviews (`docs/STABILISATION-REVIEW.md`):

```bash
bash ops/stabilisation-check.sh          # live: releases, API health/readiness, main pages, security headers, certificates, response time
```

`ops/stabilisation-evidence.sql` is pasted into phpMyAdmin → SQL on the live database. It only runs SELECTs and returns counts and timings (enquiry replies, applications, sign-ins, email, retention, visitors, content reviews), no personal data.

