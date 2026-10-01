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

Every migration is applied to a fresh database by the backend integration suite (`backend/tests/Integration`) on each CI run.
