-- Phase 2 / P2.6 (decision D-014): cookieless, self-hosted visitor analytics.
-- Safe to import more than once (IF NOT EXISTS / INSERT IGNORE).
--
-- Only daily totals are kept. To count unique visitors, each visit is reduced
-- to a SHA-256 of (a random salt for that day + IP address + browser). The salt
-- and these hashes are deleted when the day ends, so nobody (including us)
-- can link a visitor across days or back to an IP address.

-- Views and unique visitors per page per day; path '' is the whole site.
CREATE TABLE IF NOT EXISTS analytics_daily (
    day DATE NOT NULL,
    path VARCHAR(120) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    visitors INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Where visits came from (another site's domain, or '(direct)'), per day.
CREATE TABLE IF NOT EXISTS analytics_sources (
    day DATE NOT NULL,
    source VARCHAR(100) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phone, tablet or desktop, per day.
CREATE TABLE IF NOT EXISTS analytics_devices (
    day DATE NOT NULL,
    device VARCHAR(20) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (day, device)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Today only: the day's salt and the hashed visitors seen per page. Older rows
-- are deleted as soon as a new day starts.
CREATE TABLE IF NOT EXISTS analytics_salts (
    day DATE NOT NULL PRIMARY KEY,
    salt CHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_visitors (
    day DATE NOT NULL,
    path VARCHAR(120) NOT NULL,
    visitor CHAR(64) NOT NULL,
    PRIMARY KEY (day, path, visitor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- analytics.read: Administrator and Business Development see the Analytics page.
INSERT IGNORE INTO permissions (id, name) VALUES
    ('7e1f0b00-0000-4000-8000-000000000007', 'analytics.read');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000007'),
    ('7e1f0a00-0000-4000-8000-000000000002', '7e1f0b00-0000-4000-8000-000000000007');
