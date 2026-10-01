-- CW-ARCH-006 / CW-QA-010: production integrity correction.
--
-- 001 did not declare ENGINE or CHARSET, so the cPanel MariaDB host created
-- every table with its defaults: MyISAM + latin1 (verified from the
-- paxoalhu_corporate dump of 30 Sep 2026). MyISAM ignores transactions and
-- foreign keys, and latin1 cannot store many names/messages. This migration:
--   1. sets the database default charset to utf8mb4 for future tables;
--   2. converts every table to InnoDB + utf8mb4_unicode_ci;
--   3. (re)creates the foreign keys declared in 001 under the constraint names
--      InnoDB generates on a fresh install. Existing FKs are dropped first
--      (IF EXISTS, MariaDB syntax) because InnoDB will not change the charset
--      of a referenced column, so the script behaves identically on the
--      MyISAM production database and on a fresh InnoDB install, and is safe
--      to re-run.
-- Run on an empty or consistent dataset: orphaned rows will make step 3 fail.
-- Every migration after this one must declare ENGINE=InnoDB explicitly.

ALTER DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE user_roles DROP FOREIGN KEY IF EXISTS user_roles_ibfk_1, DROP FOREIGN KEY IF EXISTS user_roles_ibfk_2;
ALTER TABLE role_permissions DROP FOREIGN KEY IF EXISTS role_permissions_ibfk_1, DROP FOREIGN KEY IF EXISTS role_permissions_ibfk_2;
ALTER TABLE content_revisions DROP FOREIGN KEY IF EXISTS content_revisions_ibfk_1, DROP FOREIGN KEY IF EXISTS content_revisions_ibfk_2;
ALTER TABLE career_applications DROP FOREIGN KEY IF EXISTS career_applications_ibfk_1;
ALTER TABLE sessions DROP FOREIGN KEY IF EXISTS sessions_ibfk_1;

-- Parents first, so referenced tables are InnoDB before child FKs are added.
ALTER TABLE users ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE roles ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE permissions ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE content_items ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE career_opportunities ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE products ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE services ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE media_assets ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE enquiries ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE audit_events ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE user_roles ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE role_permissions ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE content_revisions ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE career_applications ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE sessions ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CONVERT TO changes the JSON column's binary collation; restore the JSON type.
ALTER TABLE content_revisions MODIFY content_json JSON NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE user_roles
    ADD CONSTRAINT user_roles_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id),
    ADD CONSTRAINT user_roles_ibfk_2 FOREIGN KEY (role_id) REFERENCES roles(id);
ALTER TABLE role_permissions
    ADD CONSTRAINT role_permissions_ibfk_1 FOREIGN KEY (role_id) REFERENCES roles(id),
    ADD CONSTRAINT role_permissions_ibfk_2 FOREIGN KEY (permission_id) REFERENCES permissions(id);
ALTER TABLE content_revisions
    ADD CONSTRAINT content_revisions_ibfk_1 FOREIGN KEY (content_item_id) REFERENCES content_items(id),
    ADD CONSTRAINT content_revisions_ibfk_2 FOREIGN KEY (author_id) REFERENCES users(id);
ALTER TABLE career_applications
    ADD CONSTRAINT career_applications_ibfk_1 FOREIGN KEY (career_opportunity_id) REFERENCES career_opportunities(id);
ALTER TABLE sessions
    ADD CONSTRAINT sessions_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id);
