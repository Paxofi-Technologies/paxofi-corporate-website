<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use PDO;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Database\Migrator;
use RuntimeException;

final class MigrationTest extends DatabaseTestCase
{
    private const TABLES = ['users', 'roles', 'permissions', 'user_roles', 'role_permissions', 'content_items', 'content_revisions', 'products', 'services', 'media_assets', 'enquiries', 'career_opportunities', 'career_applications', 'sessions', 'audit_events', 'login_attempts', 'recovery_codes', 'catalog_revisions', 'analytics_daily', 'analytics_sources', 'analytics_devices', 'analytics_salts', 'analytics_visitors', 'page_revisions', 'email_outbox', 'password_resets', 'job_applications', 'application_notes', 'application_uploads', 'email_attachments', 'articles', 'industries', 'content_reviews', 'redirects'];

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

    public function testStaffMigrationSeedsRolesAndCanBeImportedAgain(): void
    {
        $file = array_values(array_filter(self::migrationFiles(), static fn (string $f): bool => str_contains($f, 'staff_sign_in')))[0];
        self::applySqlFile($file);

        self::assertSame(['administrator', 'business_development', 'human_resources'], self::$pdo->query('SELECT name FROM roles ORDER BY name')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(6, (int) self::scalar("SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE p.name IN ('enquiries.read', 'enquiries.update', 'users.manage', 'audit.read')"));
        self::assertSame(2, (int) self::scalar("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.name = 'business_development' AND p.name LIKE 'enquiries.%'"));
    }

    public function testTwoFactorMigrationCanBeImportedAgain(): void
    {
        $file = array_values(array_filter(self::migrationFiles(), static fn (string $f): bool => str_contains($f, 'two_factor')))[0];
        self::applySqlFile($file);

        $columns = self::$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['totp_secret', 'totp_pending_secret', 'totp_enabled_at', 'totp_last_step'] as $column) {
            self::assertContains($column, $columns);
        }
        self::assertSame('0', (string) self::scalar("SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'mfa_pending'"));
        self::assertSame('CASCADE', (string) self::scalar("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'recovery_codes'"));
    }

    public function testSessionExpiryIsNeverAutoUpdated(): void
    {
        $extra = (string) self::scalar("SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sessions' AND COLUMN_NAME = 'expires_at'");

        self::assertStringNotContainsStringIgnoringCase('on update', $extra);
    }

    public function testSeedMigrationIsIdempotent(): void
    {
        $seed = array_values(array_filter(self::migrationFiles(), static fn (string $f): bool => str_contains($f, 'seed_published_catalog')))[0];
        self::applySqlFile($seed);

        self::assertSame(3, (int) self::scalar('SELECT COUNT(*) FROM products'), '2 from 004, PaxofiCloud from 018');
        self::assertSame(6, (int) self::scalar('SELECT COUNT(*) FROM services'), '4 from 004, 2 more from 009');
    }

    public function testCatalogMigrationMatchesTheWebsiteAndNeverOverwritesEdits(): void
    {
        self::$pdo->exec("UPDATE services SET summary = 'Edited by staff.' WHERE slug = 'digital-transformation'");
        $file = array_values(array_filter(self::migrationFiles(), static fn (string $f): bool => str_contains($f, 'editable_catalog')))[0];
        self::applySqlFile($file);

        self::assertSame('Edited by staff.', self::scalar("SELECT summary FROM services WHERE slug = 'digital-transformation'"), 'a re-import keeps edits');
        self::assertSame(['shield-check', 'cloud', 'cog'], self::$pdo->query('SELECT icon FROM products ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame('Software & Web Engineering', self::scalar('SELECT name FROM services ORDER BY sort_order LIMIT 1'));
        self::assertSame(['Transaction certainty', 'Transparency and traceability', 'Recovery built in'], json_decode((string) self::scalar("SELECT points FROM products WHERE slug = 'paxofi-pay'"), true));
        self::assertSame(['content.edit'], self::$pdo->query("SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id JOIN roles r ON r.id = rp.role_id WHERE r.name = 'business_development' AND p.name LIKE 'content.%'")->fetchAll(PDO::FETCH_COLUMN));
        self::$pdo->exec("UPDATE services SET summary = 'Modernise processes and systems with practical, measurable steps.' WHERE slug = 'digital-transformation'");
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
        self::assertSame(23, $foreignKeys, 'foreign keys: 8 declared in 001, recovery_codes.user_id (008), media uploader and product/service image and document (010), password_resets.user_id (013), job_applications.opportunity_id and application_notes.application_id (014), email_attachments.email_id (015), articles.image_id (017), industries image and document (018), content_reviews and redirects user (020)');
    }
}
