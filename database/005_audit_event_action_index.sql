-- CW-BE-012: support review queries such as "all enquiry.rate_limited events this week".
ALTER TABLE audit_events
    ADD INDEX idx_audit_action_time(action, created_at);
