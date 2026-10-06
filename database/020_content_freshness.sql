-- Phase 3 / P3.6 (decision D-025): content freshness and retirement (SRS 14.19
-- and 14.24). Safe to import more than once: tables IF NOT EXISTS.

-- When each piece of public content should next be checked. One row per item
-- that has a date or has been reviewed; items without a row show "No date".
-- item_type: page, product, service, industry, article, resource.
-- item_key: the page key (home, about, ...) or the item's id.
CREATE TABLE IF NOT EXISTS content_reviews (
    item_type VARCHAR(20) NOT NULL,
    item_key VARCHAR(64) NOT NULL,
    review_by DATE NULL,
    last_reviewed_at TIMESTAMP NULL,
    last_reviewed_by CHAR(36) NULL,
    note VARCHAR(300) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_type, item_key),
    INDEX idx_content_reviews_due (review_by),
    CONSTRAINT fk_content_reviews_user FOREIGN KEY (last_reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Addresses of retired or renamed pages, sent on (HTTP 301) to their
-- replacement by the website. from_path is stored lower-case without a
-- trailing slash, e.g. /insights/old-article.
CREATE TABLE IF NOT EXISTS redirects (
    id CHAR(36) NOT NULL PRIMARY KEY,
    from_path VARCHAR(255) NOT NULL,
    to_path VARCHAR(500) NOT NULL,
    note VARCHAR(200) NULL,
    created_by CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_redirects_from (from_path),
    CONSTRAINT fk_redirects_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
