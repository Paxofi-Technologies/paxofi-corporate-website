-- Recruitment campaign tracking (P3.1): how a candidate heard about the role,
-- the campaign tags of the link they came from (utm_source, utm_medium,
-- utm_campaign), and when the application was first reviewed (moved on from
-- "Applied"), for the 2-working-day acknowledgement target. Deleted with the
-- application (12-month retention). Safe to import more than once.
ALTER TABLE job_applications
    ADD COLUMN IF NOT EXISTS source VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS utm_source VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS first_reviewed_at TIMESTAMP NULL;

ALTER TABLE job_applications ADD INDEX IF NOT EXISTS idx_job_applications_created (created_at);

-- Applications already reviewed: their last stage change is the best record available.
UPDATE job_applications SET first_reviewed_at = stage_changed_at WHERE first_reviewed_at IS NULL AND stage <> 'applied';
