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
4. From the backend directory, run `composer install --no-dev --prefer-dist --optimize-autoloader`.
5. Confirm PCF v1.1.0 is installed under `backend/vendor/paxofi-technologies/paxofi-core-framework`.
6. Set required environment variables, including `APP_ENV=production` and the database settings.
7. Apply `database/001_initial_schema.sql`, then apply subsequent migrations in filename order (including 002 and 003).
8. Verify GET `/api/v1/health` and GET `/api/v1/readiness`.
9. Run a public contact-form smoke test and verify a persisted enquiry before production authorization.

Operational controls:
- TLS must be enabled before public traffic.
- Database credentials are environment/provider secrets.
- Backups must be enabled before production data collection.
- Do not expose backend source directories, `vendor/`, or repository metadata as public document roots.
- Do not commit `vendor/` or environment secrets.
