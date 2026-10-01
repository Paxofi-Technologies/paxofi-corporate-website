# Endpoint RTM — Version 1

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
| Admin content | /api/v1/admin/content | GET | Admin | route contract; implementation boundary |
| Admin media | /api/v1/admin/media | GET | Admin | route contract; implementation boundary |
| Admin audit | /api/v1/admin/audit | GET | Admin | route contract; implementation boundary |

Query parameters (catalogue and content): `page` (≥1), `per_page` (1–50, default 20), `slug`; content also accepts `type`. Responses include `meta.page`, `meta.per_page`, `meta.total`, `meta.total_pages`.

Error envelope: `{"success": false, "error": {"code", "message", "details?"}, "request_id"}` with codes `VALIDATION_ERROR` (422), `NOT_FOUND` (404), `METHOD_NOT_ALLOWED` (405), `RATE_LIMITED` (429, `Retry-After`), `SERVICE_UNAVAILABLE` (503), `NOT_READY` (503), `CORS_ORIGIN_DENIED` (403), `INTERNAL_ERROR` (500).

The registered public routes are asserted equal to the public entries of `backend/config/routes.php`, and every admin entry is asserted to return 404, by `backend/tests/Unit/Bootstrap/ApiApplicationTest.php`.

Every endpoint is mapped to an explicit method, route, authentication expectation and evidence location. Version 1 implementation uses the public endpoint subset required by the corporate site; admin boundaries are reserved for the next application increment.