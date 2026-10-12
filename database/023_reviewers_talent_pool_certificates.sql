-- Release 28 (decision D-029): reviewers with recusal, the talent pool, and
-- participation certificates with a public verification page.
-- Safe to import more than once.

-- Staff assigned to review an application. A reviewer who declares a conflict
-- of interest is recused: the row stays (as the record) and the application is
-- hidden from them.
CREATE TABLE IF NOT EXISTS application_reviewers (
    id CHAR(36) NOT NULL PRIMARY KEY,
    application_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'assigned',
    recusal_reason VARCHAR(500) NULL,
    assigned_by CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_reviewers (application_id, user_id),
    INDEX idx_application_reviewers_user (user_id, status),
    CONSTRAINT fk_application_reviewers_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE CASCADE,
    CONSTRAINT fk_application_reviewers_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who agreed to be contacted about future programmes. The entry copies
-- what it needs, so it outlives the application (which is deleted 12 months
-- after it closes). Withdrawing consent deletes the entry.
CREATE TABLE IF NOT EXISTS talent_pool (
    id CHAR(36) NOT NULL PRIMARY KEY,
    application_id CHAR(36) NULL,
    reference VARCHAR(16) NULL,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL,
    location VARCHAR(120) NULL,
    role_title VARCHAR(160) NOT NULL,
    engagement_type VARCHAR(12) NOT NULL,
    portfolio_url VARCHAR(500) NULL,
    linkedin_url VARCHAR(500) NULL,
    note VARCHAR(500) NULL,
    consent_given_on DATE NOT NULL,
    consent_method VARCHAR(20) NOT NULL,
    consent_expires_on DATE NOT NULL,
    added_by CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_talent_pool_email (email),
    INDEX idx_talent_pool_expiry (consent_expires_on),
    CONSTRAINT fk_talent_pool_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Participation certificates. The holder's details are copied, so a certificate
-- stays verifiable after the application is deleted. The public page shows a
-- certificate only to someone who has its code.
CREATE TABLE IF NOT EXISTS certificates (
    id CHAR(36) NOT NULL PRIMARY KEY,
    code VARCHAR(24) NOT NULL,
    application_id CHAR(36) NULL,
    holder_name VARCHAR(160) NOT NULL,
    role_title VARCHAR(160) NOT NULL,
    engagement_type VARCHAR(12) NOT NULL,
    cohort VARCHAR(12) NULL,
    started_on DATE NULL,
    completed_on DATE NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'valid',
    revoked_at TIMESTAMP NULL,
    revoked_reason VARCHAR(500) NULL,
    issued_by CHAR(36) NULL,
    issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_certificates_code (code),
    INDEX idx_certificates_application (application_id),
    CONSTRAINT fk_certificates_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Revoking and deleting certificates is for administrators only.
INSERT IGNORE INTO permissions (id, name) VALUES
    ('7e1f0b00-0000-4000-8000-000000000011', 'certificates.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000011');
