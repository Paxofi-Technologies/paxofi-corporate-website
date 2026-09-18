# cPanel Deployment — Version 1

Target:
- Next.js frontend via cPanel Application Manager / Passenger.
- Node.js 22.
- PHP 8.4 backend.
- MariaDB 10.11+ where available.

Frontend:
1. Clone the repository with cPanel Git Version Control.
2. Register the frontend directory as a Node.js application.
3. Select Node.js 22.
4. Set startup file to app.js.
5. Run npm install and npm run build.
6. Set NEXT_PUBLIC_SITE_URL and NEXT_PUBLIC_API_URL.
7. Enable the application.

Backend:
1. Point the API domain/subdomain document root to backend/public.
2. Select PHP 8.4.
3. Set required environment variables.
4. Apply database/001_initial_schema.sql to the MariaDB database.
5. Verify GET /api/v1/health and GET /api/v1/readiness.

Operational controls:
- TLS must be enabled before public traffic.
- Database credentials are environment/provider secrets.
- Backups must be enabled before production data collection.
- Do not expose backend source directories as public document roots.