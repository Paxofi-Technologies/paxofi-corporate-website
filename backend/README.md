# Backend

Pure PHP 8.4 API for the Paxofi Corporate Website, built on the Paxofi Core Framework (PCF) v1.1 consumed through Composer. PCF internals are not duplicated in this repository.

## Request lifecycle

```text
public/index.php            SAPI bridge: env → Settings → ApiApplication → emit
  └─ PCF MiddlewarePipeline
       RequestIdMiddleware        correlation id (X-Request-Id)
       SecurityHeadersMiddleware  nosniff, DENY, CSP, no-store, HSTS in production
       CorsMiddleware             exact-origin allowlist (CORS_ALLOWED_ORIGINS)
       ErrorHandlingMiddleware    application exceptions → JSON errors; 500s logged
  └─ PCF Router → Controller (Http/Controllers)
       → Application service (Application/*)      use cases, validation, abuse controls
       → Repository interface (Application/*)
       → PDO repository (Infrastructure/Persistence) via PCF PdoRepository / PdoConnection / TransactionManager
```

| Layer | Directory | PCF contracts used |
|---|---|---|
| HTTP | `src/Http` | `HttpRequest`, `HttpResponse`, `HttpMiddleware`, `Controller`, `Router`, `MiddlewarePipeline` |
| Application | `src/Application` | `TransactionManager`, `Logger` |
| Infrastructure | `src/Infrastructure` | `Repository`, `Connection`, `HealthCheck`, `HealthRegistry` |
| Composition | `src/Bootstrap` | `Environment`, `EnvLoader` |

Database connections are opened lazily, so `/health` and `/navigation` never touch the database.

## Configuration

See `../.env.example`. Settings are read from process environment variables, falling back to an optional `backend/.env` (outside the public document root).

## Tests

```bash
composer test                 # lint + all suites
composer test:unit            # no database needed
DB_HOST=127.0.0.1 DB_USERNAME=root DB_PASSWORD=... composer test:integration
```

Integration tests create a throwaway database (`DB_TEST_DATABASE`, default `cw_integration_test`), apply every migration in `../database`, and exercise the API end to end. They are skipped when `DB_HOST` is unset, and required in CI (`INTEGRATION_REQUIRED=1`).
