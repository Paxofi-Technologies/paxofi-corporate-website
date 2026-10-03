# Endpoint RTM — Version 1 and Phase 2

| Family | Route | Method | Auth | Evidence |
|---|---|---|---|---|
| Health | /api/v1/health | GET | Public | HealthController; ApiApplicationTest |
| Readiness | /api/v1/readiness | GET | Public | ReadinessController + DatabaseHealthCheck; ApiApplicationTest, PublicApiTest |
| Content | /api/v1/content | GET | Public | ContentController → ContentService → PdoContentRepository; PublicApiTest |
| Navigation | /api/v1/navigation | GET | Public | NavigationController; ApiApplicationTest |
| Products | /api/v1/products | GET | Public | CatalogController → CatalogService → PdoCatalogRepository; PublicApiTest |
| Services | /api/v1/services | GET | Public | CatalogController → CatalogService → PdoCatalogRepository; PublicApiTest |
| Careers | /api/v1/careers | GET | Public | CatalogController → CatalogService → PdoCatalogRepository; PublicApiTest |
| Forms | /api/v1/forms/{form_key}/submit | POST | Public + CORS + rate limit + honeypot | FormSubmissionController → ContactService → PdoEnquiryRepository + PdoAuditRecorder (one transaction); ContactServiceTest, PublicApiTest |
| Admin setup | /api/v1/admin/setup | GET, POST | Admin entry: trusted Origin; one-time `ADMIN_SETUP_TOKEN` (≥ 32 chars), only while no staff exist | AdminSessionController → AuthService; AdminApiTest, ApiApplicationTest |
| Admin session | /api/v1/admin/session | POST (sign in), GET (who am I), DELETE (sign out) | POST: trusted Origin + throttle (5 failures per email or 20 per IP in 15 min → 429). GET/DELETE: session cookie | AdminSessionController → AuthService, PdoSessionStore, PdoLoginAttempts; AdminApiTest, SessionCookieTest |
| Admin password | /api/v1/admin/session/password | POST | Session; current password required; other sessions revoked | AuthService::changePassword; AdminApiTest, PasswordPolicyTest |
| Admin second factor | /api/v1/admin/session/mfa | POST | Session waiting for its second factor (D-010); throttled with the password failures (5 per email / 20 per IP in 15 min); success replaces the session with a new token | AdminSessionController → AuthService::verifySecondFactor → TwoFactorService; AdminTwoFactorTest |
| Admin two-factor (own) | /api/v1/admin/account/two-factor (GET); /api/v1/admin/account/two-factor/setup, /api/v1/admin/account/two-factor/enable, /api/v1/admin/account/two-factor/recovery-codes, /api/v1/admin/account/two-factor/disable (POST) | GET, POST | Session (also allowed while an administrator still has to enrol); enable needs a code from the new secret; recovery codes need a current authenticator code; disable needs the password and is refused for administrators | AdminTwoFactorController → TwoFactorService; AdminTwoFactorTest, TotpTest, OpenSslSecretEncryptionTest |
| Admin two-factor reset | /api/v1/admin/users/{id}/two-factor/reset | POST | `users.manage`; not on yourself; signs the person out everywhere; audited `staff.two_factor.reset` | AdminTwoFactorController::reset; AdminTwoFactorTest |
| Admin enquiries | /api/v1/admin/enquiries, /api/v1/admin/enquiries/{id} | GET, GET, PATCH | `enquiries.read` (list, detail: audited `enquiry.viewed`); `enquiries.update` (status: audited `enquiry.status.<status>`) | AdminEnquiryController → AdminEnquiryService → PdoAdminEnquiryRepository; AdminApiTest |
| Admin users | /api/v1/admin/users, /api/v1/admin/users/{id} | GET, POST, PATCH | `users.manage`; no self-disable or self-demotion; last active administrator protected; sessions revoked on disable, role or password change | AdminStaffController → StaffAdminService → PdoStaffRepository; AdminApiTest |
| Admin audit | /api/v1/admin/audit | GET | `audit.read`; `action` prefix filter | AdminAuditController → PdoAuditLog; AdminApiTest |

Query parameters (catalogue, content, admin lists): `page` (≥1), `per_page` (1–50, default 20), `slug`; content also accepts `type`; admin enquiries accept `status` and `q` (name, email or company, ≤ 100 chars); admin audit accepts `action` (prefix). Responses include `meta.page`, `meta.per_page`, `meta.total`, `meta.total_pages`.

Error envelope: `{"success": false, "error": {"code", "message", "details?"}, "request_id"}` with codes `VALIDATION_ERROR` (422), `NOT_FOUND` (404), `METHOD_NOT_ALLOWED` (405), `RATE_LIMITED` (429, `Retry-After`), `SERVICE_UNAVAILABLE` (503), `NOT_READY` (503), `CORS_ORIGIN_DENIED` (403), `UNAUTHENTICATED` (401; `MFA_REQUIRED` while the second factor is pending), `FORBIDDEN` (403; `MFA_ENROLLMENT_REQUIRED` while an administrator has not set up two-factor), `CONFLICT` (409), `INTERNAL_ERROR` (500).

**Staff authentication (D-009).** The session is an opaque 256-bit token in the cookie `__Host-paxofi_admin` (HttpOnly, Secure, SameSite=Strict, Path=/); only its SHA-256 is stored (`sessions.id`). Sessions expire 8 hours after sign-in or after 30 minutes idle. Every non-GET admin request must come from an origin in `CORS_ALLOWED_ORIGINS`; CORS sends `Access-Control-Allow-Credentials: true` only to those exact origins. Passwords: Argon2id (bcrypt fallback), 12–256 characters, not the email address.

The registered routes are asserted equal to `backend/config/routes.php` (public and admin), every admin route is asserted to return 401 without a session, and admin writes from a foreign origin return 403, by `backend/tests/Unit/Bootstrap/ApiApplicationTest.php`. The staff UI at `/admin` is covered by `frontend/e2e/admin.e2e.mjs`.
