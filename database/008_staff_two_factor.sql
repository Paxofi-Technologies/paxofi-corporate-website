-- Phase 2 / P2.2 (decision D-010): two-factor sign-in (TOTP) and recovery codes.
-- Safe to import more than once (IF NOT EXISTS).

-- TOTP secrets are stored encrypted (AES-256-GCM, key MFA_ENCRYPTION_KEY in the
-- API .env), never in plain text. totp_last_step blocks reuse of a code.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) NULL AFTER last_login_at,
    ADD COLUMN IF NOT EXISTS totp_pending_secret VARCHAR(255) NULL AFTER totp_secret,
    ADD COLUMN IF NOT EXISTS totp_enabled_at TIMESTAMP NULL DEFAULT NULL AFTER totp_pending_secret,
    ADD COLUMN IF NOT EXISTS totp_last_step BIGINT NULL AFTER totp_enabled_at;

-- A session waiting for the second factor after a correct password.
ALTER TABLE sessions
    ADD COLUMN IF NOT EXISTS mfa_pending TINYINT(1) NOT NULL DEFAULT 0 AFTER revoked_at;

-- One-time recovery codes; only their SHA-256 is stored.
CREATE TABLE IF NOT EXISTS recovery_codes (
    id CHAR(36) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recovery_codes_user_code (user_id, code_hash),
    CONSTRAINT fk_recovery_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
