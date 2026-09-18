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
6. Run `npm install` and `npm run build` from `frontend`.
7. Set `NEXT_PUBLIC_SITE_URL` and `NEXT_PUBLIC_API_URL`.
8. Enable the application.

Backend:
1. Point the API domain/subdomain document root to `backend/public`.
2. Select PHP 8.4.
3. Ensure Composer 2 is available for the cPanel account.
4. Configure Composer authentication for the private PCF repository using a read-only GitHub credential. Keep the credential in Composer's global/project authentication store or an environment variable; never commit `auth.json` or a token.
5. From the backend directory, run `composer install --no-dev --prefer-dist --optimize-autoloader`.
6. Confirm PCF v1.1.x is installed under `backend/vendor/paxofi-technologies/paxofi-core-framework`.
7. Set required environment variables, including `APP_ENV=production` and the database settings.
8. Apply `database/001_initial_schema.sql`, then apply subsequent migrations in filename order (including 002 and 003).
9. Verify GET `/api/v1/health` and GET `/api/v1/readiness`.
10. Run a public contact-form smoke test and verify a persisted enquiry before production authorization.

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
- Do not commit `vendor/` or environment secrets.
