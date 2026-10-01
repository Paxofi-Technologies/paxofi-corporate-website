-- CW-BE-005 / 011.03 / 012.03: move the V1 public catalogue from hard-coded
-- API arrays into the governed products/services tables.
-- Idempotent: re-running leaves existing (possibly edited) rows untouched.
INSERT IGNORE INTO products (id, slug, name, summary, lifecycle_state, published_at) VALUES
    ('7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01', 'paxofi-pay', 'Paxofi Pay', 'Digital payments infrastructure focused on reliability, transaction certainty, transparency, recovery and trust.', 'published', '2026-09-18 00:00:00'),
    ('7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f02', 'paxofi-core-framework', 'Paxofi Core Framework', 'An independent PHP application framework for maintainable internal, client, SaaS and API systems.', 'published', '2026-09-18 00:00:00');

INSERT IGNORE INTO services (id, slug, name, summary, lifecycle_state, published_at) VALUES
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c01', 'software-engineering', 'Software Engineering', 'Web platforms, APIs and business applications built for reliability.', 'published', '2026-09-18 00:00:00'),
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c02', 'digital-products', 'Digital Products', 'Product strategy and production-ready customer experiences.', 'published', '2026-09-18 00:00:00'),
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c03', 'technology-infrastructure', 'Technology Infrastructure', 'Practical architecture, deployment and operational foundations.', 'published', '2026-09-18 00:00:00'),
    ('3c9e2d1b-8a7f-4e6d-8c5b-2a1f0e9d8c04', 'digital-growth', 'Digital Growth', 'Web, digital marketing and technology-enabled business growth.', 'published', '2026-09-18 00:00:00');
