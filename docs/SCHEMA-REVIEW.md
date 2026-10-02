# Field-level schema review — Version 1 (CW-072.01)

Database `paxoalhu_corporate`, MariaDB 11.4, all tables InnoDB / utf8mb4_unicode_ci (migration 006). Migrations 001–006 are frozen: changes ship as new numbered migrations (`database/README.md`); `bin/migrate.php` warns if an applied file changes.

**Use in v1:** *Active* = read or written by the v1 API. *Reserved* = created by 001, empty, kept for Phase 2 (decision D-005) or unused because careers live on career.paxofi.com (D-001).

## Active tables

| Table | Field | Type / constraint | Personal data | Written by | Read by | Review |
|---|---|---|---|---|---|---|
| enquiries | id | CHAR(36) PK, UUID v4 | – | ContactService | phpMyAdmin | OK |
| | name | VARCHAR(160) NOT NULL | yes | form (required, ≤ 160 chars) | | OK |
| | email | VARCHAR(255) NOT NULL, index (email, created_at) | yes | form (required, valid email, lower-cased) | rate limit | OK |
| | company | VARCHAR(255) NULL | maybe | form (optional, ≤ 255 chars) | | OK |
| | message | TEXT NOT NULL | yes | form (required, ≤ 10 000 chars) | | OK |
| | status | VARCHAR(32) DEFAULT 'new' | – | default | Business Development | OK; workflow is manual in phpMyAdmin |
| | source_ip | VARCHAR(45) NULL, index (source_ip, created_at) | yes | request | rate limit | Cleared after 90 days (D-008) |
| | user_agent | VARCHAR(500) NULL | yes | request (UTF-8 scrubbed, truncated) | abuse review | Cleared after 90 days (D-008) |
| | request_id | VARCHAR(64) NULL | – | request | support | OK; joins to audit_events |
| | created_at | TIMESTAMP, UTC | – | default | retention, rate limit | Row deleted after 24 months (D-008) |
| audit_events | id | CHAR(36) PK | – | PdoAuditRecorder, RetentionPurge | phpMyAdmin | OK |
| | actor_id | CHAR(36) NULL | – | NULL in v1 (no sign-in) | | Reserved for Phase 2 staff actions |
| | action | VARCHAR(120), index (action, created_at) | – | `enquiry.submitted`, `data_retention.purged` (rate-limit and honeypot hits go to the PHP log) | review queries | OK |
| | target_type / target_id | VARCHAR(100) / CHAR(36) NULL | – | `enquiry` + id | support | OK; no FK so audit outlives deleted enquiries |
| | outcome | VARCHAR(32) | – | `success` | | OK |
| | request_id | VARCHAR(64) NULL | – | request | support | OK |
| | created_at | TIMESTAMP, UTC | – | default | retention | Deleted after 24 months (D-008) |
| products / services | id, slug (UNIQUE), name, summary, lifecycle_state, published_at, created_at, updated_at | see 001 | – | migration 004 (seed) | `/api/v1/products`, `/services` (published only) | OK; index (lifecycle_state, published_at) serves the published query |
| content_items | id, slug (UNIQUE), title, content_type, lifecycle_state, published_at, timestamps | see 001 | – | migration 004 | `/api/v1/content` | OK |
| content_revisions | id, content_item_id FK, revision_no (UNIQUE per item), author_id FK NULL, content_json JSON, created_at | see 001/006 | – | migration 004 | latest revision per item | OK; JSON type restored in 006 |
| career_opportunities | id, slug (UNIQUE), title, description, lifecycle_state, published_at, timestamps | see 001 | – | migration 004 | `/api/v1/careers` | Kept; not used by the website (D-001) |
| schema_migrations | version PK, checksum, baseline, applied_at | 006 runner | – | migrate.php / upgrade SQL | migrate.php | OK |

## Reserved tables (empty in v1)

| Table | Purpose | Decision |
|---|---|---|
| users, roles, permissions, user_roles, role_permissions, sessions | Staff sign-in and RBAC | Phase 2 (D-005) |
| media_assets | Uploaded media | Phase 2 (D-005) |
| career_applications | Job applications | Not used: careers site (D-001); drop in a later migration if never needed |

## Findings

1. No personal data is stored outside `enquiries`; retention is automated (D-008, `bin/purge-retention.php`).
2. All text columns are utf8mb4; integration tests round-trip non-Latin text and emoji (`MigrationTest`).
3. Every query path used by v1 is indexed (published listings, rate limit by email and by IP, audit by action).
4. No outstanding schema changes for v1. Phase 2 will need: `users.password_hash` or an external identity reference, session token hashing, and a `content_items` editorial workflow — to be designed with D-005's Phase 2 scope.
