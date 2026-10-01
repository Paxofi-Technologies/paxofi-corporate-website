<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use PDO;
use Paxofi\Core\Configuration\Environment;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Database\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real MySQL/MariaDB server, rebuilding the database the way
 * production was actually built, then upgrading it the supported way:
 *
 *   1. CREATE DATABASE with a latin1 default and apply 001–003 with MyISAM as
 *      the session default engine (matches the cPanel host: see the
 *      paxoalhu_corporate dump of 30 Sep 2026);
 *   2. `Migrator::baseline('003')` to adopt that database;
 *   3. `Migrator::migrate()` to apply everything newer (004+).
 *
 * Configure with DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD (a user allowed to
 * create databases) and optionally DB_TEST_DATABASE. Without DB_HOST the
 * suite is skipped, unless INTEGRATION_REQUIRED=1 (CI), where it fails.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected const PRODUCTION_BASELINE = '003';

    protected static PDO $pdo;
    protected static Environment $environment;

    public static function setUpBeforeClass(): void
    {
        $database = getenv('DB_TEST_DATABASE') ?: 'cw_integration_test';
        self::$environment = self::environmentFor($database);

        $server = self::server();
        self::recreate($server, $database, 'CHARACTER SET latin1 COLLATE latin1_swedish_ci');

        $server->exec("USE `{$database}`");
        $server->exec('SET SESSION default_storage_engine = MyISAM');
        foreach (self::migrationFiles() as $version => $file) {
            if (strcmp(substr($version, 0, 3), self::PRODUCTION_BASELINE) > 0) {
                break;
            }
            foreach (Migrator::statements((string) file_get_contents($file)) as $statement) {
                $server->exec($statement);
            }
        }

        self::$pdo = Connection::make(self::$environment);
        $migrator = new Migrator(self::$pdo, self::migrationsDirectory());
        $migrator->baseline(self::PRODUCTION_BASELINE);
        $migrator->migrate();
    }

    protected static function environmentFor(string $database): Environment
    {
        if ((getenv('DB_HOST') ?: '') === '') {
            if (getenv('INTEGRATION_REQUIRED') === '1') {
                self::fail('INTEGRATION_REQUIRED=1 but DB_HOST is not configured.');
            }
            self::markTestSkipped('Integration database not configured (set DB_HOST).');
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            self::fail('DB_TEST_DATABASE must be a plain identifier.');
        }

        return Environment::from([
            'APP_ENV' => 'testing',
            'DB_HOST' => (string) getenv('DB_HOST'),
            'DB_PORT' => getenv('DB_PORT') ?: '3306',
            'DB_DATABASE' => $database,
            'DB_USERNAME' => (string) getenv('DB_USERNAME'),
            'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
            'CORS_ALLOWED_ORIGINS' => 'https://paxofi.com',
            'CONTACT_RATE_LIMIT_MAX' => '3',
        ]);
    }

    protected static function server(): PDO
    {
        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306'),
            (string) getenv('DB_USERNAME'),
            (string) getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    protected static function recreate(PDO $server, string $database, string $options = ''): void
    {
        $server->exec("DROP DATABASE IF EXISTS `{$database}`");
        $server->exec("CREATE DATABASE `{$database}` {$options}");
    }

    protected static function migrationsDirectory(): string
    {
        return dirname(__DIR__, 3) . '/database';
    }

    /** @return array<string, string> */
    protected static function migrationFiles(): array
    {
        return (new Migrator(new PDO('sqlite::memory:'), self::migrationsDirectory()))->available();
    }

    protected static function applySqlFile(string $file): void
    {
        foreach (Migrator::statements((string) file_get_contents($file)) as $statement) {
            self::$pdo->exec($statement);
        }
    }

    protected static function scalar(string $sql, array $parameters = []): mixed
    {
        $statement = self::$pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn();
    }
}
