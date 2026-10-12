-- Email (SMTP) settings an administrator can enter in the staff area (decision D-031).
-- A value stored here wins over the MAIL_* setting of the same name in
-- backend/.env; removing it goes back to .env. The password is stored
-- encrypted (AES-256-GCM, key derived from MFA_ENCRYPTION_KEY), never in clear.
-- Safe to import more than once.

CREATE TABLE IF NOT EXISTS mail_settings (
    setting_key VARCHAR(40) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(1000) NULL,
    updated_by CHAR(36) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
