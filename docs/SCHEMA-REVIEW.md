# Field-level schema review — Version 1 and Phase 2.1 (CW-072.01)

Database `paxoalhu_corporate`, MariaDB 11.4, all tables InnoDB / utf8mb4_unicode_ci (migration 006). Migrations 001–009 are frozen: changes ship as new numbered migrations (`database/README.md`); `bin/migrate.php` warns if an applied file changes.

**Use in v1:** *Active* = read or written by the v1 API. *Reserved* = created by 001, empty, kept for later Phase 2 slices (decision D-005) or unused because careers live on careers.paxofi.com (D-001). Phase 2.1 (migration 007, decision D-009) activates the staff sign-in tables.

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
| analytics_daily / analytics_sources / analytics_devices (011) | day + path / source / device (PK), views, visitors | 011 | – | page-view beacon (D-014) | staff Analytics page | OK; daily totals only, deleted after 25 months; at most 9 paths and 500 new sources a day |
| email_outbox (013) | kind, recipients, reply-to, subject, text, status, attempts, last error, times | 013 | yes: email addresses and enquiry text | enquiry alerts, password reset | bin/send-mail.php, after-response sending | OK; sent deleted after 30 days, failed after 90 (retention purge) |
| password_resets (013) | SHA-256 of the reset token, staff user (FK, cascade), IP, created/expires/used | 013 | IP address | *Forgot your password?* | reset page | OK; one use, 30 minutes; deleted a day after expiry |
| page_revisions (012) | page key, state (draft or published), JSON of the page's fields, author, time | 012 | staff user id (author) | staff **Content → Page text** | public `GET /pages/{page}`, staff editor | OK; one draft per page; text only, limits checked against `page-copy.json`; Privacy and Terms are not stored here |
| analytics_salts / analytics_visitors (011) | today's random salt; SHA-256 visitor hashes per page | 011 | pseudonymous, today only | page-view beacon | unique-visitor counting | OK; deleted when the day ends; no IP or user agent stored |
| catalog_revisions (009) | id, item_type (product/service), item_id, state (draft/published/created), data JSON, author_id, created_at; index (item_type, item_id, created_at) | 009 | – | staff area | staff area history | OK; at most one draft per item; no FK so history survives |
| content_items | id, slug (UNIQUE), title, content_type, lifecycle_state, published_at, timestamps | see 001 | – | migration 004 | `/api/v1/content` | OK |
| content_revisions | id, content_item_id FK, revision_no (UNIQUE per item), author_id FK NULL, content_json JSON, created_at | see 001/006 | – | migration 004 | latest revision per item | OK; JSON type restored in 006 |
| career_opportunities | id, slug (UNIQUE), title, description, lifecycle_state, published_at, timestamps; from 014: code, family, summary, content JSON (lists and assessment text), sort_order | see 001/014 | – | 004, 014 (seven PIF roles), staff area | `/api/v1/careers`, `/api/v1/careers/roles` | OK; roles on careers.paxofi.com (D-018) |
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

## Resources page (migration 019, D-023)

| Table | Field | Type / constraint | Personal data | Review |
|---|---|---|---|---|
| media_assets | resource_category, resource_summary, resource_listed_at | VARCHAR(20) NULL / VARCHAR(300) NULL / TIMESTAMP NULL; index (resource_listed_at); category values checked by the API | – | OK; listed while resource_listed_at is set; public query reads only active documents that are listed |

## Industries and product status (migration 018, D-022)

| Table | Field | Type / constraint | Personal data | Review |
|---|---|---|---|---|
| products | status | VARCHAR(20) NULL; allowed values checked by the API | – | OK; NULL shows no label |
| industries | id, slug (UNIQUE, never changes), name, label, icon, image_id / document_id FK → media_assets ON DELETE SET NULL, summary, description (TEXT, plain paragraphs), points JSON, related JSON (≤ 6 `product:`/`service:` slugs), sort_order, lifecycle_state, published_at, timestamps; index (lifecycle_state, published_at) | 018 | – | OK; drafts and history in catalog_revisions (item_type `industry`); public queries read only published, already-dated rows and link only published related items |

## News & Insights (migration 017, D-021)

| Table | Field | Type / constraint | Personal data | Review |
|---|---|---|---|---|
| articles | id, slug (UNIQUE, never changes), state (draft/published/hidden), category, title, summary, body (MEDIUMTEXT, plain text), author_name, image_id FK → media_assets, published_at (first publication), draft JSON, draft_saved_at, draft_author_id, created_by, timestamps; index (state, published_at) | 017 | author name (public by design) | OK; public queries read only published, already-dated rows |

## Recruitment tables (migration 014, D-019)

| Table | Field | Type / constraint | Personal data | Review |
|---|---|---|---|---|
| job_applications | id, reference (UNIQUE, `PIF-XXXXXX`), opportunity_id FK → career_opportunities | CHAR(36) / VARCHAR(16) | – | OK; index (opportunity_id, stage) |
| | full_name, email, phone, location, hours_per_week | VARCHAR / SMALLINT | yes | Deleted 12 months after closing or last stage change |
| | portfolio_url, linkedin_url, motivation, experience | VARCHAR(500) / TEXT | yes | As above |
| | cv_reference, cv_filename, cv_media_type, cv_size | file name in `MEDIA_STORAGE_PATH/applications` | yes (the CV) | File deleted with the row (retention and erase) |
| | privacy_version, stage, stage_changed_at, closed_at, evidence_scores JSON, interview_scores JSON | | – | OK; indexes (stage, created_at), (closed_at), (email, created_at) |
| | source_ip, user_agent | VARCHAR(45) / VARCHAR(255) NULL | yes | Cleared after 90 days |
| | source, utm_source, utm_medium, utm_campaign, first_reviewed_at (016) | VARCHAR(32) / VARCHAR(80) NULL / TIMESTAMP NULL | – (channel and campaign names) | Deleted with the application; index (created_at) for the report |
| application_notes | id, application_id FK ON DELETE CASCADE, author_id, kind (note/stage/score/email), body, created_at | | yes (may mention the candidate) | Deleted with the application |
| application_uploads | token_hash CHAR(64) PK (SHA-256), file_reference, filename, media_type, size_bytes, source_ip, created_at, claimed_at | | yes (until claimed) | Unclaimed files deleted after a day; rows a day after claiming |
| email_attachments (015) | id, email_id FK → email_outbox ON DELETE CASCADE, filename, media_type, content MEDIUMBLOB, created_at | | yes (e.g. a signed agreement) | Deleted with its email: 30 days after sending, 90 after failing |
| roles (014) | `human_resources`, with `recruitment.read`, `recruitment.manage`, `careers.edit` (also given to `administrator`) | | – | Two-factor required for the role |

## Reserved tables (empty in v1)

| Table | Purpose | Decision |
|---|---|---|
| career_applications | Job applications (001 placeholder) | Not used: superseded by `job_applications` (D-019); drop in a later migration |

## Findings

1. Personal data is stored in `enquiries`, from Phase 2.1 in the staff tables (`users`, `sessions`, `login_attempts`), and from migration 014 in the recruitment tables and CV files; retention is automated for all of them (D-008, D-019, `bin/purge-retention.php`).
2. All text columns are utf8mb4; integration tests round-trip non-Latin text and emoji (`MigrationTest`).
3. Every query path used by v1 is indexed (published listings, rate limit by email and by IP, audit by action).
4. Phase 2.1 delivered `users.password_hash`, hashed session tokens and sign-in throttling (007); Phase 2.2 the TOTP second factor with encrypted secrets and hashed recovery codes (008). Phase 2.3 added drafts and versions for products and services (`catalog_revisions`, 009); Phase 2.4 the media library (`media_assets` extended, 010). Still to design: editing other page text with `content_items`.
5. A database created from `database-install-<version>.sql` (the staging copy, D-013) has the same tables, keys and constraints as the upgraded live database. The only differences: `enquiries.message`, `products.summary`, `services.summary` and `career_opportunities.description` are `TEXT` instead of `MEDIUMTEXT` (live widened them when 006 converted it from latin1), and the JSON `points` columns may use `utf8mb4_bin`. Both hold far more than the API accepts. Checked on release 20261003-ad283c5.
