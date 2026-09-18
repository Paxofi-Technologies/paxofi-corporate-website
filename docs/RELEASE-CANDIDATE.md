# Corporate Website Release Candidate
Date: 2026-09-18
Version: 1.0.0-rc.1

Scope:
- Public corporate pages: home, about, services, products, careers, contact.
- Responsive accessible shell and navigation.
- PHP API boundary with health, content, navigation, careers and governed form submission.
- Versioned initial MariaDB/MySQL schema.
- CI validation and container packaging.

Design status:
- This is the implementation-first release candidate. Figma redesign is intentionally deferred to v2.

Release gate:
- Source is in GitHub.
- Automated validation is required before tagging production.
- Actual hosting compatibility, DNS/TLS, production secrets, backup/restore and final smoke test must be verified in the target environment before production release.