-- Phase 3 / P3.4 (decision D-023): the public Resources page. An administrator
-- lists documents from the media library there, each with a category and a
-- short description. A document is listed while resource_listed_at is set.
-- Safe to import more than once.
ALTER TABLE media_assets
    ADD COLUMN IF NOT EXISTS resource_category VARCHAR(20) NULL AFTER title,
    ADD COLUMN IF NOT EXISTS resource_summary VARCHAR(300) NULL AFTER resource_category,
    ADD COLUMN IF NOT EXISTS resource_listed_at TIMESTAMP NULL AFTER resource_summary,
    ADD INDEX IF NOT EXISTS idx_media_resource_listed (resource_listed_at);
