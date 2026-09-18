# Endpoint RTM — Version 1

| Family | Route | Method | Auth | Evidence |
|---|---|---|---|---|
| Health | /api/v1/health | GET | Public | backend/public/index.php |
| Readiness | /api/v1/readiness | GET | Public | backend/public/index.php |
| Content | /api/v1/content | GET | Public | backend/public/index.php |
| Navigation | /api/v1/navigation | GET | Public | backend/public/index.php |
| Products | /api/v1/products | GET | Public | backend/public/index.php |
| Services | /api/v1/services | GET | Public | backend/public/index.php |
| Careers | /api/v1/careers | GET | Public | backend/public/index.php |
| Forms | /api/v1/forms/{form_key}/submit | POST | Public + rate limit | backend/public/index.php |
| Admin content | /api/v1/admin/content | GET | Admin | route contract; implementation boundary |
| Admin media | /api/v1/admin/media | GET | Admin | route contract; implementation boundary |
| Admin audit | /api/v1/admin/audit | GET | Admin | route contract; implementation boundary |

Every endpoint is mapped to an explicit method, route, authentication expectation and evidence location. Version 1 implementation uses the public endpoint subset required by the corporate site; admin boundaries are reserved for the next application increment.