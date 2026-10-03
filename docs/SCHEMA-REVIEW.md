# Field-level schema review — Version 1 and Phase 2.1 (CW-072.01)

Database `paxoalhu_corporate`, MariaDB 11.4, all tables InnoDB / utf8mb4_unicode_ci (migration 006). Migrations 001–009 are frozen: changes ship as new numbered migrations (`database/README.md`); `bin/migrate.php` warns if an applied file changes.

**Use in v1:** *Active* = read or written by the v1 API. *Reserved* = created by 001, empty, kept for later Phase 2 slices (decision D-005) or unused because careers live on career.paxofi.com (D-001). Phase 2.1 (migration 007, decision D-009) activates the staff sign-in tables.

## Active tables

| Table | Field | Type / constraint | Personal data | Written by | Read by | Review |
|---|---|---|---|---|---|---|
| enquiries | id | CHAR(36) PK, UUID v4 | – | ContactService | phpMyAdmin | OK |
| | name | VARCHAR(160) NOT NULL | yes | form (required, ≤ 160 chars) | | OK |
| | email | VARCHAR(255) NOT NULL, index (email, created_at) | yes | form (required, valid email, lower-cased) | rate limit | OK |
| | company | VARCHAR(255) NULL | maybe | form (optional, ≤ 255 chars) | | OK |
| | message | TEXT NOT NULL | yes | form (required, ≤ 10 000 chars) | | OK |
| | status | VARCHAR(32) DEFAULT 'new' | – | default; staff area (`new`, `in_progress`, `replied`, `closed`, `spam`) | inbox filter and counts | OK; validated by `EnquiryStatus`; changes audited |
| | source_ip | VARCHAR(45) NULL, index (source_ip, created_at) | yes | request | rate limit | Cleared after 90 days (D-008) |
| | user_agent | VARCHAR(500) NULL | yes | request (UTF-8 scrubbed, truncated) | abuse review | Cleared after 90 days (D-008) |
| | request_id | VARCHAR(64) NULL | – | request | support | OK; joins to audit_events |
| | created_at | TIMESTAMP, UTC, index `idx_enquiries_created` (007) | – | default | inbox order, retention, rate limit | Row deleted after 24 months (D-008) |
| audit_events | id | CHAR(36) PK | – | PdoAuditRecorder, RetentionPurge | phpMyAdmin | OK |
| | actor_id | CHAR(36) NULL | – | staff user id for staff actions; NULL for visitors and the system | audit log (joined to `users` for the name) | OK; no FK, so audit outlives a removed account |
| | action | VARCHAR(120), index (action, created_at) | – | `enquiry.submitted`, `data_retention.purged`; staff: `staff.setup`, `staff.sign_in`, `staff.sign_out`, `staff.password_changed`, `staff.created`, `staff.updated`, `enquiry.viewed`, `enquiry.status.<status>` | audit log (prefix filter) | OK |
| | target_type / target_id | VARCHAR(100) / CHAR(36) NULL | – | `enquiry` + id | support | OK; no FK so audit outlives deleted enquiries |
| | outcome | VARCHAR(32) | – | `success`, `failure`, `denied` | audit log | OK |
| | request_id | VARCHAR(64) NULL | – | request | support | OK |
| | created_at | TIMESTAMP, UTC | – | default | retention | Deleted after 24 months (D-008) |
| products / services | id, slug (UNIQUE), name, summary, lifecycle_state, published_at, created_at, updated_at; from 009: label VARCHAR(60), icon VARCHAR(40), points JSON, sort_order INT | see 001/009 | – | migrations 004/009 (seed); staff area (D-011) | `/api/v1/products`, `/services` (published only, by sort_order) | OK; small tables, ordering in memory; shown = lifecycle_state 'published' |
| products / services (010) | image_id, document_id | CHAR(36) NULL, FK → media_assets ON DELETE SET NULL | – | staff area (D-012) | public API resolves them to the picture and download | OK; files in use cannot be deleted |
| media_assets (001, 010) | id, kind (`image`/`document`), filename (safe download name), media_type (checked MIME), storage_reference (= id), lifecycle_state, size_bytes, width/height (images), alt_text (images), title (documents), sha256, uploaded_by FK → users ON DELETE SET NULL, created_at, updated_at; index (kind, created_at) | 001/010 | uploader id only | staff area (D-012) | `/api/v1/media/{id}/{filename}`, staff area | OK; file bytes live in `MEDIA_STORAGE_PATH`, not in the database; no visitor data |
| catalog_revisions (009) | id, item_type (product/service), item_id, state (draft/published/created), data JSON, author_id, created_at; index (item_type, item_id, created_at) | 009 | – | staff area | staff area history | OK; at most one draft per item; no FK so history survives |
| content_items | id, slug (UNIQUE), title, content_type, lifecycle_state, published_at, timestamps | see 001 | – | migration 004 | `/api/v1/content` | OK |
| content_revisions | id, content_item_id FK, revision_no (UNIQUE per item), author_id FK NULL, content_json JSON, created_at | see 001/006 | – | migration 004 | latest revision per item | OK; JSON type restored in 006 |
| career_opportunities | id, slug (UNIQUE), title, description, lifecycle_state, published_at, timestamps | see 001 | – | migration 004 | `/api/v1/careers` | Kept; not used by the website (D-001) |
| schema_migrations | version PK, checksum, baseline, applied_at | 006 runner | – | migrate.php / upgrade SQL | migrate.php | OK |

## Staff sign-in tables (Phase 2.1, migration 007, D-009)

| Table | Field | Type / constraint | Personal data | Review |
|---|---|---|---|---|
| users | id, email (UNIQUE), status (`active`/`disabled`), created_at, updated_at | see 001 | email | OK |
| | display_name | VARCHAR(160) NULL | name | OK |
| | password_hash | VARCHAR(255) NULL | – (Argon2id/bcrypt hash) | OK; never returned by the API |
| | password_changed_at, last_login_at | TIMESTAMP NULL | – | OK |
| roles / permissions / role_permissions / user_roles | ids, slugs, names | see 001; seeded by 007: roles `administrator`, `business_development`; permissions `enquiries.read`, `enquiries.update`, `users.manage`, `audit.read` | – | OK; one role per user in the UI |
| sessions | id | CHAR(64) PK = SHA-256 of the cookie token | – | OK; the token itself is never stored |
| | user_id FK, expires_at (8 h absolute), revoked_at, last_seen_at (30 min idle), created_at | see 001/007 | – | OK; `expires_at` has no ON UPDATE (MigrationTest) |
| | source_ip, user_agent | VARCHAR(45) / VARCHAR(500) NULL | yes | Deleted with the session 30 days after it ends (D-008 extension) |
| login_attempts | id, email, source_ip, succeeded, created_at; indexes (email, created_at), (source_ip, created_at) | 007 | yes | Throttle window 15 min (password and second-factor failures together); rows deleted after 90 days |
| users (008) | totp_secret, totp_pending_secret | VARCHAR(255) NULL, AES-256-GCM ("v1." + base64), key from `MFA_ENCRYPTION_KEY` | – (secret) | OK; never returned by the API |
| | totp_enabled_at, totp_last_step | TIMESTAMP NULL / BIGINT NULL | – | OK; last_step makes each code single-use |
| sessions (008) | mfa_pending | TINYINT(1) DEFAULT 0 | – | OK; pending sessions last 5 minutes |
| recovery_codes (008) | id PK, user_id FK ON DELETE CASCADE, code_hash CHAR(64) (UNIQUE per user), used_at, created_at | 008 | – | OK; SHA-256 only, 50-bit codes; replaced as a set |

## Reserved tables (empty in v1)

| Table | Purpose | Decision |
|---|---|---|
| career_applications | Job applications | Not used: careers site (D-001); drop in a later migration if never needed |

## Findings

1. Personal data is stored in `enquiries` and, from Phase 2.1, in the staff tables (`users`, `sessions`, `login_attempts`); retention is automated for all of them (D-008, `bin/purge-retention.php`).
2. All text columns are utf8mb4; integration tests round-trip non-Latin text and emoji (`MigrationTest`).
3. Every query path used by v1 is indexed (published listings, rate limit by email and by IP, audit by action).
4. Phase 2.1 delivered `users.password_hash`, hashed session tokens and sign-in throttling (007); Phase 2.2 the TOTP second factor with encrypted secrets and hashed recovery codes (008). Phase 2.3 added drafts and versions for products and services (`catalog_revisions`, 009); Phase 2.4 the media library (`media_assets` extended, 010). Still to design: editing other page text with `content_items`.
