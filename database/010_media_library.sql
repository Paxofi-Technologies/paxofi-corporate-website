-- Phase 2 / P2.4 (decision D-012): media library (images and documents) and
-- an optional image and document on each product and service.
-- Safe to import more than once (IF NOT EXISTS).

-- media_assets (001) gains what the staff area needs. The files themselves live
-- outside the website and API folders (MEDIA_STORAGE_PATH), named by id;
-- storage_reference keeps that name.
ALTER TABLE media_assets
    ADD COLUMN IF NOT EXISTS kind VARCHAR(20) NOT NULL DEFAULT 'image' AFTER id,
    ADD COLUMN IF NOT EXISTS width INT UNSIGNED NULL AFTER size_bytes,
    ADD COLUMN IF NOT EXISTS height INT UNSIGNED NULL AFTER width,
    ADD COLUMN IF NOT EXISTS alt_text VARCHAR(200) NULL AFTER height,
    ADD COLUMN IF NOT EXISTS title VARCHAR(120) NULL AFTER alt_text,
    ADD COLUMN IF NOT EXISTS sha256 CHAR(64) NULL AFTER title,
    ADD COLUMN IF NOT EXISTS uploaded_by CHAR(36) NULL AFTER sha256,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX IF NOT EXISTS idx_media_kind_created (kind, created_at),
    ADD CONSTRAINT fk_media_uploaded_by FOREIGN KEY IF NOT EXISTS (uploaded_by) REFERENCES users (id) ON DELETE SET NULL;

-- Optional picture and downloadable document (e.g. a brochure) per item.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS image_id CHAR(36) NULL AFTER icon,
    ADD COLUMN IF NOT EXISTS document_id CHAR(36) NULL AFTER image_id,
    ADD CONSTRAINT fk_products_image FOREIGN KEY IF NOT EXISTS (image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_products_document FOREIGN KEY IF NOT EXISTS (document_id) REFERENCES media_assets (id) ON DELETE SET NULL;

ALTER TABLE services
    ADD COLUMN IF NOT EXISTS image_id CHAR(36) NULL AFTER icon,
    ADD COLUMN IF NOT EXISTS document_id CHAR(36) NULL AFTER image_id,
    ADD CONSTRAINT fk_services_image FOREIGN KEY IF NOT EXISTS (image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_services_document FOREIGN KEY IF NOT EXISTS (document_id) REFERENCES media_assets (id) ON DELETE SET NULL;
