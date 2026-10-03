# Database

MySQL/MariaDB schema, migration and seed boundary for the Paxofi Corporate Website.

Migration controls:

- ordered and versioned migrations
- additive corrective migrations after release
- integrity and uniqueness constraints
- index/query verification
- fresh-install and upgrade-path testing
- no credentials or secrets in seed data

Migration register (apply in filename order):

| File | Purpose |
|---|---|
| `001_initial_schema.sql` | V1 baseline schema (identity, content, catalogue, media, enquiries, careers, sessions, audit) |
| `002_enquiry_metadata.sql` | Enquiry source IP, user agent, request id; email/time index |
| `003_enquiry_rate_limit_index.sql` | Source IP/time index for contact rate limiting |
| `004_seed_published_catalog.sql` | Seeds the published V1 products and services (idempotent) |
| `005_audit_event_action_index.sql` | Action/time index for audit review queries |
| `006_innodb_utf8mb4_and_foreign_keys.sql` | Converts all tables to InnoDB + utf8mb4 and (re)creates the 001 foreign keys (production was built MyISAM + latin1) |
| `007_staff_sign_in_and_roles.sql` | Phase 2 (D-009): user name/password columns, session activity, `login_attempts`, enquiry date index, roles `administrator` / `business_development` and their permissions |
| `008_staff_two_factor.sql` | Phase 2 (D-010): encrypted TOTP secret, pending secret, enabled date and last used step on `users`; `sessions.mfa_pending`; `recovery_codes` (SHA-256 only) |

## Applying migrations

Always use the runner; never paste migration files into phpMyAdmin:

```bash
cd backend
php bin/migrate.php --status         # what is applied / pending
php bin/migrate.php                  # apply pending migrations, recorded in schema_migrations
```

The production database `paxoalhu_corporate` was built by hand with 001–003 before migrations were tracked. Adopt it **once** with `php bin/migrate.php --baseline=003`, then run `php bin/migrate.php`. The runner refuses to touch a database that has application tables but no history, so 001 can never be re-run against production by mistake.

## Rules for new migrations

- Never edit a migration that has been applied anywhere; add a new numbered file (the runner warns when an applied file changes).
- Every `CREATE TABLE` must declare `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. The cPanel host defaults to MyISAM + latin1.
- Statements are split on `;` outside quotes and comments; do not use stored procedures or `DELIMITER`.
- MariaDB commits DDL immediately, so a migration that fails part-way is not rolled back: take the backup (the deploy script does) and fix forward.

## Verification

On every CI run the integration suite rebuilds the database the way production was built (latin1 database, MyISAM for 001–003), baselines it at 003, applies the rest with the runner, and asserts InnoDB, utf8mb4, all 8 foreign keys, Unicode round-trips and transactional rollback. It separately applies every migration to a fresh database on server defaults and asserts the same end state.
