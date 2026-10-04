-- Phase 2 / P2.8 (decision D-016): emails, staff password reset by email.
-- Safe to import more than once (IF NOT EXISTS).
--
-- email_outbox: every email the API sends (new-enquiry alerts, password
-- reset links) is written here first and then sent by the API or by the
-- bin/send-mail.php cron job, with retries. Sent emails are deleted after
-- 30 days and failed ones after 90 (retention purge), as they contain
-- personal data.
CREATE TABLE IF NOT EXISTS email_outbox (
    id CHAR(36) NOT NULL PRIMARY KEY,
    kind VARCHAR(40) NOT NULL,
    recipients VARCHAR(1000) NOT NULL,
    reply_to VARCHAR(255) NULL,
    subject VARCHAR(255) NOT NULL,
    body_text MEDIUMTEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    next_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_email_outbox_due (status, next_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One-time password reset links: only a SHA-256 of the token is stored. A
-- link expires after 30 minutes and works once.
CREATE TABLE IF NOT EXISTS password_resets (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    user_id CHAR(36) NOT NULL,
    request_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    INDEX idx_password_resets_user (user_id, created_at),
    INDEX idx_password_resets_ip (request_ip, created_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
