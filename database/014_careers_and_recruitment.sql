-- Careers site and recruitment (decisions D-018, D-019): careers.paxofi.com.
-- Safe to import more than once (IF NOT EXISTS / INSERT IGNORE).
--
-- career_opportunities (from 001) becomes the list of roles shown on the
-- careers site, with the details each role page needs. job_applications holds
-- applications; CVs are stored as files outside the website folders
-- (MEDIA_STORAGE_PATH/applications), never served publicly. Unsuccessful
-- applications are deleted 12 months after they close (retention purge).

ALTER TABLE career_opportunities
    ADD COLUMN IF NOT EXISTS code VARCHAR(4) NULL,
    ADD COLUMN IF NOT EXISTS family VARCHAR(80) NULL,
    ADD COLUMN IF NOT EXISTS summary VARCHAR(300) NULL,
    ADD COLUMN IF NOT EXISTS content JSON NULL,
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 100;

-- The seven PIF 2026 role families (PIF 2026 Final Launch Production Pack, Role Activation Pack).
INSERT IGNORE INTO career_opportunities (id, slug, title, description, lifecycle_state, published_at, code, family, summary, content, sort_order) VALUES
    ('7e1f0c00-0000-4000-8000-000000000001', 'project-manager-fellow', 'Project Manager Fellow', 'Coordinate assigned Paxofi projects from planning through execution and completion.', 'published', CURRENT_TIMESTAMP, 'PM', 'Project and programme management', 'Coordinate assigned Paxofi projects from planning through execution and completion.', '{"responsibilities": ["Build actionable project plans, milestones and dependencies", "Own task tracking, resourcing and project documentation", "Coordinate teams, run meetings and follow up on action items", "Track delivery progress and manage risks and issues", "Produce status reports, closure reports and lessons-learned reviews", "Support process improvement across assigned projects"], "deliverables": ["Project plans and work breakdown structures", "Schedules and task trackers", "Risk and issue registers", "Status reports", "Closure reports and lessons learned"], "competencies": ["Planning and organisation", "Communication", "Leadership", "Agile awareness", "Task management", "Documentation", "Risk management", "Accountability", "Coordination"], "tools": [], "evidence": "Evidence of a project plan, status report or coordination example (academic, freelance, volunteer or personal projects all count).", "assessment": "Project-planning case study plus a prioritisation exercise.", "interview": "Ownership, delivery judgement, stakeholder management, conflict handling and risk awareness."}', 10),
    ('7e1f0c00-0000-4000-8000-000000000002', 'program-coordinator-fellow', 'Program Coordinator Fellow', 'Support planning, administration, communication and day-to-day coordination of Paxofi programmes.', 'published', CURRENT_TIMESTAMP, 'PC', 'Project and programme management', 'Support planning, administration, communication and day-to-day coordination of Paxofi programmes.', '{"responsibilities": ["Administer programme activities, schedules and records", "Coordinate participant communication and onboarding support", "Organise workshops, sessions and learning events", "Track attendance, participation and completion", "Maintain programme documentation and prepare reports", "Support assessments, evaluations and continuous improvement"], "deliverables": ["Programme calendars", "Participant trackers", "Attendance records", "Communication plans", "Programme reports and dashboards"], "competencies": ["Coordination", "Organisation", "Communication", "Scheduling", "Documentation", "Participant support", "Reporting", "Attention to detail"], "tools": [], "evidence": "Evidence of a schedule, participant tracker or operational plan you have built or maintained.", "assessment": "Programme-coordination scenario exercise.", "interview": "Organisation, communication, escalation judgement and service orientation."}', 20),
    ('7e1f0c00-0000-4000-8000-000000000003', 'graphics-creative-designer-fellow', 'Graphics / Creative Designer Fellow', 'Create visual materials that communicate Paxofi''s brand, products, campaigns and programmes clearly and professionally.', 'published', CURRENT_TIMESTAMP, 'GD', 'Product and creative design', 'Create visual materials that communicate Paxofi''s brand, products, campaigns and programmes clearly and professionally.', '{"responsibilities": ["Produce digital graphics, social media designs and promotional materials", "Build presentations, campaign assets and platform-specific adaptations", "Apply Paxofi brand standards consistently", "Develop visual concepts and support marketing initiatives", "Collaborate with marketing, product and communications teams", "Keep design files organised and versioned"], "deliverables": ["Social media and campaign graphics", "Presentations", "Brand-consistent promotional materials"], "competencies": ["Typography", "Composition and hierarchy", "Colour and contrast", "Branding", "Digital and social design", "Creativity", "Attention to detail", "Responsiveness to feedback"], "tools": ["CorelDRAW", "Adobe Photoshop", "Adobe Illustrator", "Adobe InDesign", "Figma", "Canva or equivalent"], "evidence": "A portfolio or design samples: branding, social media design, adverts, illustration, presentations or campaign work.", "assessment": "Recruitment creative-brief challenge.", "interview": "Design rationale, iteration process, brand consistency and deadline management."}', 30),
    ('7e1f0c00-0000-4000-8000-000000000004', 'software-engineer-fellow', 'Software Engineer Fellow', 'Take part in the design, development, testing, documentation and improvement of Paxofi software systems while building professional engineering capability.', 'published', CURRENT_TIMESTAMP, 'SE', 'Technology and engineering', 'Take part in the design, development, testing, documentation and improvement of Paxofi software systems while building professional engineering capability.', '{"responsibilities": ["Contribute to backend functionality, APIs and databases", "Work with frontend engineers on integrated features", "Write and maintain tests, and debug and fix defects", "Follow Git and GitHub workflows and take part in code review", "Practise secure coding and write technical documentation", "Collaborate within an Agile engineering team"], "deliverables": ["Working, tested features", "Pull requests with reviews", "Technical documentation"], "competencies": ["Programming fundamentals", "PHP and OOP", "SQL", "APIs and HTTP", "Databases", "Git", "Testing", "Debugging", "Security fundamentals", "Problem solving"], "tools": ["PHP 8+", "Paxofi Core Framework (PCF)", "MySQL / MariaDB", "Redis", "REST APIs", "Git / GitHub", "Docker", "Nginx", "CI/CD"], "evidence": "A code sample or project contribution you can walk us through (solo, academic, open source or team-based).", "assessment": "Practical coding and problem-solving task.", "interview": "Technical reasoning, architecture thinking, debugging approach and collaboration."}', 40),
    ('7e1f0c00-0000-4000-8000-000000000005', 'frontend-developer-fellow', 'Frontend Developer Fellow', 'Build and improve responsive user interfaces with the approved Paxofi frontend stack and engineering standards.', 'published', CURRENT_TIMESTAMP, 'FD', 'Technology and engineering', 'Build and improve responsive user interfaces with the approved Paxofi frontend stack and engineering standards.', '{"responsibilities": ["Build responsive interfaces and reusable components", "Implement approved designs with attention to detail", "Integrate APIs and authentication flows", "Improve accessibility, performance and responsive behaviour", "Test and debug across browsers and devices", "Work closely with UX/UI designers and backend engineers"], "deliverables": ["Responsive pages and components", "Accessible, tested interfaces", "Pull requests with reviews"], "competencies": ["HTML and CSS", "JavaScript and TypeScript", "React", "Next.js", "Responsive design", "API integration", "Git", "Browser debugging", "Performance and accessibility fundamentals"], "tools": ["React", "Next.js", "TypeScript", "Tailwind CSS", "Bootstrap 5", "REST APIs", "Git / GitHub"], "evidence": "A code sample, deployed project or repository that shows your frontend work.", "assessment": "Frontend implementation task.", "interview": "Component architecture, accessibility, performance, state and data handling, and debugging."}', 50),
    ('7e1f0c00-0000-4000-8000-000000000006', 'hr-officer-fellow', 'HR Officer Fellow', 'Support recruitment, candidate administration, people documentation, onboarding coordination and HR operations.', 'published', CURRENT_TIMESTAMP, 'HR', 'People and HR', 'Support recruitment, candidate administration, people documentation, onboarding coordination and HR operations.', '{"responsibilities": ["Support recruitment campaigns and application review", "Coordinate candidate communication, shortlisting and interview scheduling", "Support onboarding and keep HR records", "Help with participant support and performance-support processes", "Coordinate learning activities and communicate policy updates", "Keep all HR documentation and reporting confidential"], "deliverables": ["Candidate trackers and communications", "Onboarding records", "HR reports"], "competencies": ["HR fundamentals", "Recruitment administration", "Documentation", "Candidate communication", "Onboarding", "Confidentiality", "Integrity", "Empathy", "Organisation", "Professional judgement"], "tools": [], "evidence": "Evidence of organisation, communication and confidentiality: a coordination example, HR-related coursework or relevant experience.", "assessment": "Recruitment-operations case exercise.", "interview": "Candidate experience, confidentiality, fairness, process control and escalation judgement."}', 60),
    ('7e1f0c00-0000-4000-8000-000000000007', 'ux-ui-designer-fellow', 'UX/UI Designer Fellow', 'Support user research, information architecture, interaction design, visual systems, prototyping and usability improvement.', 'published', CURRENT_TIMESTAMP, 'UX', 'Product and creative design', 'Support user research, information architecture, interaction design, visual systems, prototyping and usability improvement.', '{"responsibilities": ["Conduct user research and define user problems and flows", "Build wireframes, high-fidelity interfaces and prototypes", "Contribute to and maintain design systems", "Run usability testing and iterate on findings", "Write clear design documentation for developer handoff", "Review implementation against approved designs"], "deliverables": ["User flows and wireframes", "High-fidelity designs and prototypes", "Usability findings", "Design handoff documentation"], "competencies": ["Figma", "Wireframing and prototyping", "User flows", "Information architecture", "Responsive design", "Visual hierarchy and typography", "Usability", "Design systems", "Accessibility fundamentals"], "tools": ["Figma", "Paxofi UI design system"], "evidence": "A portfolio: apps, websites, dashboards, design systems, prototypes or UX case studies.", "assessment": "UX/UI case study or redesign exercise.", "interview": "Research rationale, usability reasoning, iteration and stakeholder communication."}', 70);

CREATE TABLE IF NOT EXISTS job_applications (
    id CHAR(36) NOT NULL PRIMARY KEY,
    reference VARCHAR(16) NOT NULL,
    opportunity_id CHAR(36) NOT NULL,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(40) NULL,
    location VARCHAR(120) NULL,
    hours_per_week TINYINT UNSIGNED NOT NULL,
    portfolio_url VARCHAR(500) NULL,
    linkedin_url VARCHAR(500) NULL,
    motivation TEXT NOT NULL,
    experience TEXT NOT NULL,
    cv_reference CHAR(36) NULL,
    cv_filename VARCHAR(200) NULL,
    cv_media_type VARCHAR(120) NULL,
    cv_size INT UNSIGNED NULL,
    privacy_version VARCHAR(20) NOT NULL,
    stage VARCHAR(32) NOT NULL DEFAULT 'applied',
    stage_changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    evidence_scores JSON NULL,
    interview_scores JSON NULL,
    closed_at TIMESTAMP NULL,
    source_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_job_applications_reference (reference),
    INDEX idx_job_applications_stage (stage, created_at),
    INDEX idx_job_applications_email (email, created_at),
    INDEX idx_job_applications_ip (source_ip, created_at),
    INDEX idx_job_applications_closed (closed_at),
    CONSTRAINT fk_job_applications_opportunity FOREIGN KEY (opportunity_id) REFERENCES career_opportunities (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff notes, sent emails and stage changes on an application.
CREATE TABLE IF NOT EXISTS application_notes (
    id CHAR(36) NOT NULL PRIMARY KEY,
    application_id CHAR(36) NOT NULL,
    author_id CHAR(36) NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'note',
    body TEXT NOT NULL,
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    INDEX idx_application_notes (application_id, created_at),
    CONSTRAINT fk_application_notes_application FOREIGN KEY (application_id) REFERENCES job_applications (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A CV uploaded before the form is sent; claimed by the application within a day, else deleted.
CREATE TABLE IF NOT EXISTS application_uploads (
    token_hash CHAR(64) NOT NULL PRIMARY KEY,
    file_reference CHAR(36) NOT NULL,
    filename VARCHAR(200) NOT NULL,
    media_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    source_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP NULL,
    INDEX idx_application_uploads_ip (source_ip, created_at),
    INDEX idx_application_uploads_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- HR staff (D-019): see and manage applications and roles; nothing else.
INSERT IGNORE INTO roles (id, name) VALUES
    ('7e1f0a00-0000-4000-8000-000000000003', 'human_resources');

INSERT IGNORE INTO permissions (id, name) VALUES
    ('7e1f0b00-0000-4000-8000-000000000008', 'recruitment.read'),
    ('7e1f0b00-0000-4000-8000-000000000009', 'recruitment.manage'),
    ('7e1f0b00-0000-4000-8000-000000000010', 'careers.edit');

INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000008'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000009'),
    ('7e1f0a00-0000-4000-8000-000000000001', '7e1f0b00-0000-4000-8000-000000000010'),
    ('7e1f0a00-0000-4000-8000-000000000003', '7e1f0b00-0000-4000-8000-000000000008'),
    ('7e1f0a00-0000-4000-8000-000000000003', '7e1f0b00-0000-4000-8000-000000000009'),
    ('7e1f0a00-0000-4000-8000-000000000003', '7e1f0b00-0000-4000-8000-000000000010');
