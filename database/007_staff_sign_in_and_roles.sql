-- Phase 2 / P2.1 (decision D-009): staff sign-in, sessions, login throttling and roles.
-- Safe to import more than once (IF NOT EXISTS / INSERT IGNORE).

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(160) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS password_changed_at TIMESTAMP NULL DEFAULT NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL DEFAULT NULL AFTER password_changed_at;

-- sessions.id holds the SHA-256 hash of the session token, never the token itself.
ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL DEFAULT NULL AFTER expires_at,
    ADD COLUMN IF NOT EXISTS source_ip VARCHAR(45) NULL AFTER revoked_at,
    ADD COLUMN IF NOT EXISTS user_agent VARCHAR(500) NULL AFTER source_ip;

CREATE TABLE IF NOT EXISTS login_attempts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    source_ip VARCHAR(45) NULL,
    succeeded TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_email_time (email, created_at),
    INDEX idx_login_attempts_ip_time (source_ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin list filters enquiries by status, newest first.
ALTER TABLE enquiries
    ADD INDEX IF NOT EXISTS idx_enquiries_created (created_at);

INSERT IGNORE INTO roles (id, name) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', 'administrator'),
    ('7e1f0a00-0000-4000-8000-000000000002', 'business_development');

INSERT IGNORE INTO permissions (id, name) VALUES
    ('7e1f0b00-0000-4000-8000-000000000001', 'enquiries.read'),
    ('7e1f0b00-0000-4000-8000-000000000002', 'enquiries.update'),
    ('7e1f0b00-0000-4000-8000-000000000003', 'users.manage'),
    ('7e1f0b00-0000-4000-8000-000000000004', 'audit.read');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000001'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000002'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000003'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000004'),
    ('7e1f0a00-0000-4000-8000-000000000002', '7e1f0b00-0000-4000-8000-000000000001'),
    ('7e1f0a00-0000-4000-8000-000000000002', '7e1f0b00-0000-4000-8000-000000000002');
