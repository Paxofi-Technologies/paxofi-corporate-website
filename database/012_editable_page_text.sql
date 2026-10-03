-- Phase 2 / P2.7 (decision D-015): page text edited in the staff area.
-- Safe to import more than once (IF NOT EXISTS).
--
-- The editable fields of each page and their built-in wording are listed in
-- backend/config/page-copy.json (the website keeps an identical copy). This
-- table keeps each page's draft (at most one) and every published version;
-- the newest published version is what the website shows. A page never
-- published shows its built-in wording.
CREATE TABLE IF NOT EXISTS page_revisions (
    id CHAR(36) NOT NULL PRIMARY KEY,
    page VARCHAR(40) NOT NULL,
    state VARCHAR(20) NOT NULL,
    data JSON NOT NULL,
    author_id CHAR(36) NULL,
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_page_revisions_page (page, state, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
