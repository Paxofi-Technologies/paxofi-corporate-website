<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use PDO;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Database\Migrator;
use RuntimeException;

final class MigrationTest extends DatabaseTestCase
{
    private const TABLES = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'content_items', 'content_revisions', 'products', 'services', 'media_assets', 'enquiries', 'career_opportunities', 'career_applications', 'sessions', 'audit_events'];

    public function testProductionShapedDatabaseIsUpgradedToInnoDbUtf8mb4(): void
    {
        self::assertSchemaIsSound(self::$pdo);
    }

    public function testMigrationHistoryRecordsBaselineAndAppliedVersions(): void
    {
        $rows = self::$pdo->query('SELECT version, baseline FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_KEY_PAIR);

        self::assertSame(array_keys(self::migrationFiles()), array_keys($rows));
        foreach ($rows as $version => $baseline) {
            self::assertSame(strcmp(substr((string) $version, 0, 3), self::PRODUCTION_BASELINE) <= 0 ? 1 : 0, (int) $baseline, (string) $version);
        }
    }

    public function testRunningAgainIsANoOp(): void
    {
        $migrator = new Migrator(self::$pdo, self::migrationsDirectory());

        self::assertSame([], $migrator->pending());
        self::assertSame([], $migrator->migrate());
        self::assertSame([], $migrator->modified());
    }

    public function testUnicodeTextRoundTrips(): void
    {
        $name = 'Ọlá Ẹniọlá 李雷 😀';
        self::$pdo->prepare("INSERT INTO enquiries (id, name, email, message) VALUES (UUID(), :name, 'unicode@example.com', :message)")->execute(['name' => $name, 'message' => $name]);

        self::assertSame($name, self::scalar("SELECT name FROM enquiries WHERE email = 'unicode@example.com'"));
    }

    public function testForeignKeysAreEnforced(): void
    {
        $this->expectException(\PDOException::class);
        self::$pdo->exec("INSERT INTO content_revisions (id, content_item_id, revision_no, content_json) VALUES (UUID(), 'missing-item', 1, '{}')");
    }

    public function testRateLimitAndAuditIndexesExist(): void
    {
        $enquiryIndexes = self::$pdo->query('SHOW INDEX FROM enquiries')->fetchAll(PDO::FETCH_COLUMN, 2);
        $auditIndexes = self::$pdo->query('SHOW INDEX FROM audit_events')->fetchAll(PDO::FETCH_COLUMN, 2);

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

    public function testFreshInstallOnServerDefaultsReachesTheSameSchema(): void
    {
        $database = self::$environment->get('DB_DATABASE') . '_fresh';
        self::recreate(self::server(), $database);
        $pdo = Connection::make(self::environmentFor($database));

        $applied = (new Migrator($pdo, self::migrationsDirectory()))->migrate();

        self::assertSame(array_keys(self::migrationFiles()), $applied);
        self::assertSchemaIsSound($pdo);
    }

    public function testRefusesToRunAgainstAnUntrackedExistingDatabase(): void
    {
        $database = self::$environment->get('DB_DATABASE') . '_untracked';
        self::recreate(self::server(), $database);
        $pdo = Connection::make(self::environmentFor($database));
        $pdo->exec('CREATE TABLE enquiries (id CHAR(36) PRIMARY KEY)');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--baseline=003');
        (new Migrator($pdo, self::migrationsDirectory()))->migrate();
    }

    public function testBaselineIsRefusedOnceHistoryExists(): void
    {
        $this->expectException(RuntimeException::class);
        (new Migrator(self::$pdo, self::migrationsDirectory()))->baseline('003');
    }

    private static function assertSchemaIsSound(PDO $pdo): void
    {
        $tables = $pdo->query("SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> 'schema_migrations'")->fetchAll(PDO::FETCH_ASSOC);

        self::assertEqualsCanonicalizing(self::TABLES, array_column($tables, 'TABLE_NAME'));
        foreach ($tables as $table) {
            self::assertSame('InnoDB', $table['ENGINE'], $table['TABLE_NAME'] . ' engine');
            self::assertStringStartsWith('utf8mb4_', (string) $table['TABLE_COLLATION'], $table['TABLE_NAME'] . ' collation');
        }

        $foreignKeys = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')->fetchColumn();
        self::assertSame(8, $foreignKeys, 'foreign keys declared in 001');
    }
}
