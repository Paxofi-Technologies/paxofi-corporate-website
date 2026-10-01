<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

final class MigrationTest extends DatabaseTestCase
{
    public function testAllMigrationsApplyToAFreshDatabase(): void
    {
        $tables = self::$pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        foreach (['users', 'roles', 'permissions', 'content_items', 'content_revisions', 'products', 'services', 'media_assets', 'enquiries', 'career_opportunities', 'career_applications', 'sessions', 'audit_events'] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function testRateLimitAndAuditIndexesExist(): void
    {
        $enquiryIndexes = self::$pdo->query('SHOW INDEX FROM enquiries')->fetchAll(\PDO::FETCH_COLUMN, 2);
        $auditIndexes = self::$pdo->query('SHOW INDEX FROM audit_events')->fetchAll(\PDO::FETCH_COLUMN, 2);

        self::assertContains('idx_enquiries_email_created', $enquiryIndexes);
        self::assertContains('idx_enquiries_source_ip_created', $enquiryIndexes);
        self::assertContains('idx_audit_action_time', $auditIndexes);
    }

    public function testSeedMigrationIsIdempotent(): void
    {
        $seed = array_values(array_filter(self::migrationFiles(), static fn (string $f): bool => str_contains($f, 'seed_published_catalog')))[0];
        self::applySqlFile($seed);

        self::assertSame(2, (int) self::scalar('SELECT COUNT(*) FROM products'));
        self::assertSame(4, (int) self::scalar('SELECT COUNT(*) FROM services'));
    }
}
