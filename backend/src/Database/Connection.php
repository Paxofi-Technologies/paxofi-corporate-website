<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Database;

use PDO;
use PDOException;
use Paxofi\Core\Configuration\Environment;
use RuntimeException;

/** Builds the application's PDO handle from the PCF environment contract. */
final class Connection
{
    public static function make(Environment $environment): PDO
    {
        $host = $environment->get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = $environment->get('DB_PORT', '3306') ?? '3306';
        $database = $environment->get('DB_DATABASE', '') ?? '';
        $username = $environment->get('DB_USERNAME', '') ?? '';
        $password = $environment->get('DB_PASSWORD', '') ?? '';

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            // Store and compare timestamps in UTC regardless of server defaults.
            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed.', 0, $e);
        }
    }
}
