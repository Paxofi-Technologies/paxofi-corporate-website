ALTER TABLE enquiries
    ADD INDEX idx_enquiries_source_ip_created(source_ip, created_at);
