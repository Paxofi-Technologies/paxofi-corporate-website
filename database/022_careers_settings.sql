-- Careers settings an administrator can change in the staff area (decision D-028):
-- the day applications open, the last day for Cohort 1, and the hash of the
-- staff test code. A value stored here wins over the server setting of the same
-- name (CAREERS_APPLICATIONS_OPEN_FROM, CAREERS_COHORT1_CLOSES, CAREERS_TEST_CODE).
-- Safe to import more than once.

CREATE TABLE IF NOT EXISTS careers_settings (
    setting_key VARCHAR(40) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NULL,
    updated_by CHAR(36) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
