-- Phase 3 / P3.3 and P3.5 (decision D-022): product status labels and the
-- Industries section. Safe to import more than once: columns IF NOT EXISTS,
-- status set only where still empty, new rows INSERT IGNORE / NOT EXISTS.

-- P3.3: every product says how far along it is (SRS 14.7). Allowed values are
-- checked by the API: planned, in_development, pilot, beta, available,
-- limited, paused, retired. NULL shows no label.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) NULL AFTER label;

-- Statuses from the product records in PKDMS, 5 October 2026: Paxofi Pay is in
-- programme design (planned); PCF v1.0.0 and v1.1.0 are released and run this
-- website's API (available).
UPDATE products SET status = 'planned' WHERE slug = 'paxofi-pay' AND status IS NULL;
UPDATE products SET status = 'available' WHERE slug = 'paxofi-core-framework' AND status IS NULL;

-- PaxofiCloud (PKDMS: PaxofiCloud Product Master Blueprint v1.0), in development.
-- Skipped if staff already added it under any of these names.
INSERT INTO products (id, slug, name, label, status, icon, summary, points, sort_order, lifecycle_state, published_at)
SELECT '7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f03', 'paxoficloud', 'PaxofiCloud', 'Paxofi Product', 'in_development', 'cloud',
       'A cloud and digital infrastructure platform for domains, web hosting, cloud servers, SSL and business email, bought, managed and supported through one Paxofi account.',
       JSON_ARRAY('Domains and DNS management', 'Web hosting and cloud servers', 'One account, one dashboard'),
       15, 'published', '2026-10-05 00:00:00'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM products
    WHERE slug IN ('paxoficloud', 'paxofi-cloud') OR REPLACE(LOWER(name), ' ', '') = 'paxoficloud'
);

-- P3.5: sectors Paxofi serves (SRS 4.6 and 13.8), edited in the staff area
-- like products and services: live columns here, drafts and history in
-- catalog_revisions (item_type 'industry').
-- related: up to 6 "product:<slug>" / "service:<slug>" references; the website
-- links only those that are published.
CREATE TABLE IF NOT EXISTS industries (
    id CHAR(36) NOT NULL PRIMARY KEY,
    slug VARCHAR(180) NOT NULL,
    name VARCHAR(255) NOT NULL,
    label VARCHAR(60) NULL,
    icon VARCHAR(40) NULL,
    image_id CHAR(36) NULL,
    document_id CHAR(36) NULL,
    summary TEXT NOT NULL,
    description TEXT NULL,
    points JSON NULL,
    related JSON NULL,
    sort_order INT NOT NULL DEFAULT 100,
    lifecycle_state VARCHAR(32) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_industries_slug (slug),
    INDEX idx_industries_state_pub (lifecycle_state, published_at),
    CONSTRAINT fk_industries_image FOREIGN KEY (image_id) REFERENCES media_assets (id) ON DELETE SET NULL,
    CONSTRAINT fk_industries_document FOREIGN KEY (document_id) REFERENCES media_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The ten target industries of SRS 4.6, with their example solutions. Wording
-- describes what Paxofi can build; it claims no clients, certifications or
-- regulated status (SRS 13.8).
INSERT IGNORE INTO industries (id, slug, name, icon, summary, description, points, related, sort_order, lifecycle_state, published_at) VALUES
('4d1e0a00-0000-4000-8000-000000000001', 'financial-services', 'Financial Services', 'banknote',
 'Software for payments, financial operations and automation, built with the reliability, traceability and security that money demands.',
 'Financial products depend on trust. Every transaction has to be accounted for, every failure recoverable and every change traceable.\n\nWe design and build fintech platforms, payment integrations and back-office automation with those requirements at the core, and we are designing Paxofi Pay on the same principles. Regulated activities stay with licensed partners; our part is the software.',
 JSON_ARRAY('Payment and collection integrations', 'Financial operations and reconciliation tools', 'Back-office process automation', 'Secure APIs for partners and channels'),
 JSON_ARRAY('product:paxofi-pay', 'service:api-platform-development', 'service:software-web-engineering', 'service:cloud-infrastructure-foundations'),
 10, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000002', 'education', 'Education', 'graduation-cap',
 'Learning platforms, student portals and training systems that make teaching, enrolment and administration easier to run.',
 'Schools, training providers and universities need systems that students, staff and parents can use without friction, often on a phone and a modest connection.\n\nWe build learning and training platforms, student and parent portals, and the administration tools behind them, designed to be accessible and simple to maintain.',
 JSON_ARRAY('Learning and training platforms', 'Student and parent portals', 'Enrolment, fees and records management', 'Online assessments and certificates'),
 JSON_ARRAY('service:software-web-engineering', 'service:product-strategy-prototyping', 'service:digital-transformation'),
 20, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000003', 'healthcare', 'Healthcare', 'heart-pulse',
 'Health information systems and appointment platforms designed with privacy, reliability and ease of use in mind.',
 'Health providers handle some of the most sensitive information there is, and their staff have little time for systems that get in the way.\n\nWe design health information systems, appointment and scheduling platforms and patient-facing services with privacy and access control from the start, and with workflows shaped around the people who use them.',
 JSON_ARRAY('Appointment and scheduling platforms', 'Health information and records systems', 'Patient portals and reminders', 'Reporting for operations and management'),
 JSON_ARRAY('service:software-web-engineering', 'service:api-platform-development', 'service:cloud-infrastructure-foundations'),
 30, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000004', 'retail-commerce', 'Retail & Commerce', 'shopping-cart',
 'E-commerce, inventory and customer management systems that help businesses sell online and run their stores efficiently.',
 'Selling today means running a shop, a website and several digital channels at once, and keeping stock, orders and customers in step across all of them.\n\nWe build online stores, inventory and order systems and customer relationship tools, and connect them to the payment and delivery services a business already uses.',
 JSON_ARRAY('Online stores and e-commerce', 'Inventory and order management', 'Customer relationship (CRM) tools', 'Payment and delivery integrations'),
 JSON_ARRAY('service:software-web-engineering', 'service:digital-marketing-growth', 'service:api-platform-development', 'product:paxofi-pay'),
 40, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000005', 'logistics-transportation', 'Logistics & Transportation', 'truck',
 'Fleet management and tracking systems that give operators a clear, current view of vehicles, deliveries and drivers.',
 'Logistics depends on knowing where things are and what happens next. Paper and spreadsheets stop keeping up once a fleet or delivery network grows.\n\nWe build fleet and delivery management systems, tracking dashboards and customer notifications, and connect them to the devices and services already in use.',
 JSON_ARRAY('Fleet and vehicle management', 'Shipment and delivery tracking', 'Dispatch and route planning tools', 'Customer delivery notifications'),
 JSON_ARRAY('service:software-web-engineering', 'service:api-platform-development', 'service:cloud-infrastructure-foundations'),
 50, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000006', 'government', 'Government', 'landmark',
 'Digital public services and workflow automation that make it simpler for citizens to get things done and for agencies to deliver.',
 'Public services work best when people can complete them online, clearly and securely, and when the processes behind them are automated and auditable.\n\nWe design and build digital service portals, case and workflow systems and records management, treating accessibility, security and audit trails as requirements rather than extras.',
 JSON_ARRAY('Online service and application portals', 'Workflow and case management', 'Records and document management', 'Accessible, auditable systems'),
 JSON_ARRAY('service:digital-transformation', 'service:software-web-engineering', 'service:cloud-infrastructure-foundations'),
 60, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000007', 'agriculture', 'Agriculture', 'sprout',
 'Agritech platforms and farm management tools that help producers, cooperatives and buyers plan, track and trade.',
 'Agriculture runs on timing, weather and markets, and many producers still manage it on paper.\n\nWe build farm management and record-keeping tools, cooperative and out-grower platforms, and marketplaces that connect producers with buyers, designed to work well on a phone in the field.',
 JSON_ARRAY('Farm management and record keeping', 'Cooperative and out-grower platforms', 'Produce marketplaces and ordering', 'Mobile-first tools for the field'),
 JSON_ARRAY('service:product-strategy-prototyping', 'service:software-web-engineering', 'service:api-platform-development'),
 70, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000008', 'manufacturing', 'Manufacturing', 'factory',
 'Production and operations management systems that connect planning, the factory floor and reporting.',
 'Manufacturers need to know what is being produced, with what, and at what cost. Disconnected spreadsheets make that hard.\n\nWe build production planning, inventory and quality systems and operational dashboards that bring the data together, connected to existing equipment and business software where possible.',
 JSON_ARRAY('Production planning and tracking', 'Materials and inventory management', 'Quality and maintenance records', 'Operational dashboards and reporting'),
 JSON_ARRAY('service:digital-transformation', 'service:software-web-engineering', 'service:api-platform-development'),
 80, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000009', 'non-profit', 'Non-Profit Organisations', 'hand-heart',
 'Donation platforms and volunteer management systems that help charities and NGOs raise funds and coordinate their people.',
 'Non-profit organisations need to reach supporters, account for every donation and coordinate volunteers, usually with small teams and tight budgets.\n\nWe build donation and fundraising platforms, volunteer and programme management tools and clear reporting, kept simple to run and maintain.',
 JSON_ARRAY('Donation and fundraising platforms', 'Volunteer management', 'Programme and beneficiary records', 'Reporting for donors and boards'),
 JSON_ARRAY('service:software-web-engineering', 'service:digital-marketing-growth', 'product:paxofi-pay'),
 90, 'published', '2026-10-05 00:00:00'),
('4d1e0a00-0000-4000-8000-000000000010', 'startups-smes', 'Startups & SMEs', 'rocket',
 'Digital transformation, software development and business automation for growing companies, from first product to scale.',
 'Startups and small businesses need technology that fits their stage: a product that can be tested quickly, systems that save time, and foundations that will not need rebuilding as they grow.\n\nWe help shape and prototype products, build web and mobile applications, automate routine work and set up reliable hosting, and we are building PaxofiCloud to make that infrastructure easier to buy and manage.',
 JSON_ARRAY('Product strategy and prototypes', 'Web and mobile applications', 'Business process automation', 'Hosting, domains and cloud set-up'),
 JSON_ARRAY('service:product-strategy-prototyping', 'service:software-web-engineering', 'service:digital-transformation', 'product:paxoficloud'),
 100, 'published', '2026-10-05 00:00:00');
