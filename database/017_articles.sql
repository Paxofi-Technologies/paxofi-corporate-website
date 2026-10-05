-- News & Insights (P3.2, decision D-021): articles on the corporate website,
-- written in the staff area. Like products and services (D-011), each article
-- has its live content and at most one draft: saving changes only the draft,
-- and an administrator publishes it. An article is shown only while it is
-- published. Safe to import more than once.
CREATE TABLE IF NOT EXISTS articles (
    id CHAR(36) NOT NULL PRIMARY KEY,
    slug VARCHAR(170) NOT NULL,
    state VARCHAR(16) NOT NULL DEFAULT 'draft',
    category VARCHAR(20) NOT NULL DEFAULT 'insight',
    title VARCHAR(160) NOT NULL,
    summary VARCHAR(300) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    author_name VARCHAR(80) NULL,
    image_id CHAR(36) NULL,
    published_at TIMESTAMP NULL,
    draft JSON NULL,
    draft_saved_at TIMESTAMP NULL,
    draft_author_id CHAR(36) NULL,
    created_by CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_articles_slug (slug),
    INDEX idx_articles_published (state, published_at),
    CONSTRAINT fk_articles_image FOREIGN KEY (image_id) REFERENCES media_assets (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
