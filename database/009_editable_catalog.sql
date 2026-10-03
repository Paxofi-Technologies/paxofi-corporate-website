-- Phase 2 / P2.3 (decision D-011): products and services edited from the staff area.
-- Safe to import more than once: columns IF NOT EXISTS, content updates only touch
-- rows still holding the original seed text, new rows INSERT IGNORE.

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS label VARCHAR(60) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS icon VARCHAR(40) NULL AFTER label,
    ADD COLUMN IF NOT EXISTS points JSON NULL AFTER summary,
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 100 AFTER points;

ALTER TABLE services
    ADD COLUMN IF NOT EXISTS label VARCHAR(60) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS icon VARCHAR(40) NULL AFTER label,
    ADD COLUMN IF NOT EXISTS points JSON NULL AFTER summary,
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 100 AFTER points;

-- Bring the seeded rows in line with the wording on the live website (V1 copy).
UPDATE products SET label = 'Paxofi Product', icon = 'shield-check', sort_order = 10,
    summary = 'Digital payments infrastructure designed around reliability, transaction certainty, transparency, recovery and trust.',
    points = JSON_ARRAY('Transaction certainty', 'Transparency and traceability', 'Recovery built in')
WHERE id = '7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01' AND label IS NULL
  AND summary = 'Digital payments infrastructure focused on reliability, transaction certainty, transparency, recovery and trust.';

UPDATE products SET label = 'Paxofi Technology', icon = 'cog', sort_order = 20,
    points = JSON_ARRAY('Layered, testable architecture', 'Secure HTTP and data foundations', 'Built for long-lived systems')
WHERE id = '7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f02' AND label IS NULL;

UPDATE services SET slug = 'software-web-engineering', name = 'Software & Web Engineering', icon = 'code', sort_order = 10,
    summary = 'Web platforms and business applications engineered for reliability, security and maintainability.'
WHERE id = '3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c01' AND icon IS NULL AND summary = 'Web platforms, APIs and business applications built for reliability.';

UPDATE services SET slug = 'product-strategy-prototyping', name = 'Product Strategy & Prototyping', icon = 'rocket', sort_order = 30,
    summary = 'Clarify the problem, shape the product and validate it with working prototypes.'
WHERE id = '3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c02' AND icon IS NULL AND summary = 'Product strategy and production-ready customer experiences.';

UPDATE services SET slug = 'cloud-infrastructure-foundations', name = 'Cloud & Infrastructure Foundations', icon = 'cloud', sort_order = 50,
    summary = 'Deployment, hosting, monitoring and recovery foundations that keep services running.'
WHERE id = '3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c03' AND icon IS NULL AND summary = 'Practical architecture, deployment and operational foundations.';

UPDATE services SET slug = 'digital-marketing-growth', name = 'Digital Marketing & Growth', icon = 'megaphone', sort_order = 60,
    summary = 'Web presence, content and digital channels that help the right people find you.'
WHERE id = '3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c04' AND icon IS NULL AND summary = 'Web, digital marketing and technology-enabled business growth.';

INSERT IGNORE INTO services (id, slug, name, icon, summary, sort_order, lifecycle_state, published_at) VALUES
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c05', 'api-platform-development', 'API & Platform Development', 'network', 'Well-documented APIs and platform services that connect products, partners and data.', 20, 'published', '2026-10-03 00:00:00'),
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c06', 'digital-transformation', 'Digital Transformation', 'workflow', 'Modernise processes and systems with practical, measurable steps.', 40, 'published', '2026-10-03 00:00:00');

-- Every saved draft and every publication, so any version can be restored.
CREATE TABLE IF NOT EXISTS catalog_revisions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    item_type VARCHAR(20) NOT NULL,
    item_id CHAR(36) NOT NULL,
    state VARCHAR(20) NOT NULL,
    data JSON NOT NULL,
    author_id CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_catalog_revisions_item (item_type, item_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- content.edit: save drafts. content.publish: put changes live, hide or show items.
INSERT IGNORE INTO permissions (id, name) VALUES
    ('7e1f0b00-0000-4000-8000-000000000005', 'content.edit'),
    ('7e1f0b00-0000-4000-8000-000000000006', 'content.publish');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000005'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000006'),
    ('7e1f0a00-0000-4000-8000-000000000002', '7e1f0b00-0000-4000-8000-000000000005');
