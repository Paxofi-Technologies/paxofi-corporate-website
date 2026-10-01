# cPanel Deployment — Version 1

Target:
- Next.js frontend via cPanel Application Manager / Passenger.
- Node.js 22.
- PHP 8.4 backend.
- MariaDB 10.11+ where available.
- PCF v1.1.0 consumed by Composer under backend/vendor/.

Release source:
- cPanel Git repository tracks `main` only for production deployment.
- Engineering changes flow through feature branch → `develop` → `main`.
- Do not deploy an unmerged feature branch to production.

Frontend:
1. Clone the repository with cPanel Git Version Control.
2. Keep the production checkout on the `main` branch.
3. Register the `frontend` directory as a Node.js application.
4. Select Node.js 22.
5. Set startup file to `app.js`.
6. Run `npm ci` and `npm run build` from `frontend` (installs the exact versions pinned in `package-lock.json`).
7. Set `NEXT_PUBLIC_SITE_URL` (e.g. `https://paxofi.com`) and `NEXT_PUBLIC_API_URL` to the API **base** (e.g. `https://api.paxofi.com/api/v1`) **before** `npm run build` — Next.js inlines `NEXT_PUBLIC_*` values at build time. The contact form posts to `{NEXT_PUBLIC_API_URL}/forms/contact/submit`.
8. Enable the application.

Backend:
1. Point the API domain/subdomain document root to `backend/public`.
2. Select PHP 8.4.
3. Ensure Composer 2 is available for the cPanel account.
4. Configure Composer authentication for the private PCF repository using a read-only GitHub credential. Keep the credential in Composer's global/project authentication store or an environment variable; never commit `auth.json` or a token.
5. From the backend directory, run `composer install --no-dev --prefer-dist --optimize-autoloader`.
6. Confirm PCF v1.1.x is installed under `backend/vendor/paxofi-technologies/paxofi-core-framework`.
7. Configure the backend environment. Either set real environment variables, or create `backend/.env` from `.env.example` (it sits outside the `backend/public` document root; permissions `600`). Required in production:
   - `APP_ENV=production`
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `CORS_ALLOWED_ORIGINS=https://paxofi.com,https://www.paxofi.com` (exact frontend origins; without this, browsers block the contact form)
8. Apply `database/001_initial_schema.sql`, then every subsequent migration in filename order (002 → 005). `004_seed_published_catalog.sql` loads the V1 products and services; it is safe to re-run.
9. Verify GET `/api/v1/health` returns 200 and GET `/api/v1/readiness` returns 200 with `"database": true`.
10. Verify GET `/api/v1/products` returns the two seeded products.
11. Verify the CORS preflight from the live frontend origin:
    `curl -i -X OPTIONS https://api.paxofi.com/api/v1/forms/contact/submit -H "Origin: https://paxofi.com" -H "Access-Control-Request-Method: POST"` → `204` with `Access-Control-Allow-Origin: https://paxofi.com`.
12. Submit the public contact form from the live site and confirm one new row in `enquiries` and a matching `enquiry.submitted` row in `audit_events` (same `request_id`) before production authorization.

Composer private-repository requirement:
- The PCF repository is private, so Composer must have read access when resolving the VCS dependency.
- A fine-grained GitHub credential scoped to the PCF repository with read-only contents access is sufficient.
- Do not place credentials in `composer.json`, source files, Git history, or public document roots.
- If cPanel SSH keys are used instead, the PCF repository may be accessed through a read-only GitHub deploy key.

Operational controls:
- TLS must be enabled before public traffic.
- Database credentials are environment/provider secrets.
- Backups must be enabled before production data collection.
- Do not expose backend source directories, `vendor/`, or repository metadata as public document roots.
- Do not commit `vendor/`, `backend/.env` or environment secrets.
- API errors are logged as JSON lines to PHP's stderr/error log with the request id; give support the `X-Request-Id` response header value when reporting a problem.
