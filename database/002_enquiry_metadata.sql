ALTER TABLE enquiries
    ADD COLUMN source_ip VARCHAR(45) NULL AFTER status,
    ADD COLUMN user_agent VARCHAR(500) NULL AFTER source_ip,
    ADD COLUMN request_id VARCHAR(64) NULL AFTER user_agent,
    ADD INDEX idx_enquiries_email_created(email, created_at);
