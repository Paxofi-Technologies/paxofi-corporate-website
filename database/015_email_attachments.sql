-- Email attachments (decision D-019 follow-up): files sent with a candidate
-- email, such as the signed-offer PIF Participant Agreement. Kept with the
-- queued email and deleted with it (sent emails after 30 days, failed ones
-- after 90; retention purge). Safe to import more than once.
CREATE TABLE IF NOT EXISTS email_attachments (
    id CHAR(36) NOT NULL PRIMARY KEY,
    email_id CHAR(36) NOT NULL,
    filename VARCHAR(160) NOT NULL,
    media_type VARCHAR(120) NOT NULL,
    content MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_attachments_email (email_id),
    CONSTRAINT fk_email_attachments_email FOREIGN KEY (email_id) REFERENCES email_outbox (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
