<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use PDO;
use Paxofi\Core\Configuration\Environment;
use Paxofi\CorporateWebsite\Database\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Runs against a real MySQL/MariaDB server. Each test class gets a freshly
 * created database with every migration in database/ applied in filename
 * order, which doubles as fresh-install migration evidence.
 *
 * Configure with DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD (a user allowed to
 * create databases) and optionally DB_TEST_DATABASE. Without DB_HOST the
 * suite is skipped, unless INTEGRATION_REQUIRED=1 (CI), where it fails.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static PDO $pdo;
    protected static Environment $environment;

    public static function setUpBeforeClass(): void
    {
        if ((getenv('DB_HOST') ?: '') === '') {
            if (getenv('INTEGRATION_REQUIRED') === '1') {
                self::fail('INTEGRATION_REQUIRED=1 but DB_HOST is not configured.');
            }
            self::markTestSkipped('Integration database not configured (set DB_HOST).');
        }

        $database = getenv('DB_TEST_DATABASE') ?: 'cw_integration_test';
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            self::fail('DB_TEST_DATABASE must be a plain identifier.');
        }

        $server = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306'),
            (string) getenv('DB_USERNAME'),
            (string) getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $server->exec("DROP DATABASE IF EXISTS `{$database}`");
        $server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        self::$environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_HOST' => (string) getenv('DB_HOST'),
            'DB_PORT' => getenv('DB_PORT') ?: '3306',
            'DB_DATABASE' => $database,
            'DB_USERNAME' => (string) getenv('DB_USERNAME'),
            'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
            'CORS_ALLOWED_ORIGINS' => 'https://paxofi.com',
            'CONTACT_RATE_LIMIT_MAX' => '3',
        ]);
        self::$pdo = Connection::make(self::$environment);

        foreach (self::migrationFiles() as $file) {
            self::applySqlFile($file);
        }
    }

    /** @return list<string> */
    protected static function migrationFiles(): array
    {
        $files = glob(dirname(__DIR__, 3) . '/database/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    protected static function applySqlFile(string $file): void
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
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
