<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Database;

use PDO;
use PDOException;
use Paxofi\Core\Configuration\Environment;
use RuntimeException;

final class Connection
{
    public static function make(): PDO
    {
        $processEnvironment = getenv();
        $environment = Environment::from(
            is_array($processEnvironment) ? array_map('strval', $processEnvironment) : [],
        );

        $host = $environment->get('DB_HOST', '127.0.0.1') ?? '127.0.0.1';
        $port = $environment->get('DB_PORT', '3306') ?? '3306';
        $database = $environment->get('DB_DATABASE', '') ?? '';
        $username = $environment->get('DB_USERNAME', '') ?? '';
        $password = $environment->get('DB_PASSWORD', '') ?? '';

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database,
        );

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed.', 0, $e);
        }
    }
}
