<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Database;

use PDO;
use RuntimeException;

/**
 * Applies database/NNN_*.sql files in order and records each one in
 * `schema_migrations`, so a migration can never run twice.
 *
 * Databases created before this runner existed (e.g. production, built by
 * hand from 001–003) are adopted once with baseline(): it records the given
 * versions as applied without executing them.
 *
 * MariaDB commits DDL implicitly, so a migration is not atomic: if one fails
 * part-way, fix forward with a new migration (see database/README.md).
 */
final class Migrator
{
    private const TABLE = 'schema_migrations';

    /** @var list<string> */
    private array $log = [];

    public function __construct(private readonly PDO $pdo, private readonly string $directory)
    {
    }

    /** @return array<string, string> version => absolute path, in apply order */
    public function available(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $migrations[basename($file, '.sql')] = $file;
        }

        return $migrations;
    }

    /** @return array<string, array{applied_at: string, checksum: string}> */
    public function applied(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query('SELECT version, applied_at, checksum FROM ' . self::TABLE . ' ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);

        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['version']] = ['applied_at' => (string) $row['applied_at'], 'checksum' => (string) $row['checksum']];
        }

        return $applied;
    }

    /** @return list<string> versions not yet applied */
    public function pending(): array
    {
        return array_values(array_diff(array_keys($this->available()), array_keys($this->applied())));
    }

    /** @return list<string> applied versions whose file content has changed since */
    public function modified(): array
    {
        $available = $this->available();
        $changed = [];
        foreach ($this->applied() as $version => $record) {
            if (isset($available[$version]) && $record['checksum'] !== '' && $record['checksum'] !== self::checksum($available[$version])) {
                $changed[] = $version;
            }
        }

        return $changed;
    }

    /**
     * Records every migration up to and including $throughPrefix (e.g. "003")
     * as applied without running it. Only allowed on an untracked database.
     *
     * @return list<string> versions recorded
     */
    public function baseline(string $throughPrefix): array
    {
        if (preg_match('/^\d{3}$/', $throughPrefix) !== 1) {
            throw new RuntimeException('Baseline must be a three-digit migration number, e.g. 003.');
        }
        if ($this->applied() !== []) {
            throw new RuntimeException('Baseline refused: this database already tracks migrations.');
        }

        $recorded = [];
        foreach ($this->available() as $version => $file) {
            if (strcmp(substr($version, 0, 3), $throughPrefix) > 0) {
                break;
            }
            $this->record($version, $file, baseline: true);
            $recorded[] = $version;
        }

        if ($recorded === []) {
            throw new RuntimeException(sprintf('No migrations found up to %s.', $throughPrefix));
        }

        return $recorded;
    }

    /** @return list<string> versions applied by this call */
    public function migrate(): array
    {
        if ($this->applied() === [] && $this->hasApplicationTables()) {
            throw new RuntimeException(
                'This database already contains application tables but no migration history. '
                . 'Record what it already has first, e.g. `php bin/migrate.php --baseline=003`.',
            );
        }

        $available = $this->available();
        $done = [];
        foreach ($this->pending() as $version) {
            foreach (self::statements((string) file_get_contents($available[$version])) as $statement) {
                $this->pdo->exec($statement);
            }
            $this->record($version, $available[$version], baseline: false);
            $done[] = $version;
        }

        return $done;
    }

    /**
     * Splits a SQL file into statements on semicolons outside quotes and
     * comments. Sufficient for plain DDL/DML migrations (no procedures).
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $quote !== '`') {
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $current .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            }
            if ($char === ';') {
                if (trim($current) !== '') {
                    $statements[] = trim($current);
                }
                $current = '';
                continue;
            }
            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = trim($current);
        }

        return $statements;
    }

    public static function checksum(string $file): string
    {
        return hash_file('sha256', $file) ?: '';
    }

    private function ensureTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            version VARCHAR(191) NOT NULL PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            baseline TINYINT(1) NOT NULL DEFAULT 0,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function record(string $version, string $file, bool $baseline): void
    {
        $statement = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (version, checksum, baseline) VALUES (:version, :checksum, :baseline)');
        $statement->execute(['version' => $version, 'checksum' => self::checksum($file), 'baseline' => $baseline ? 1 : 0]);
    }

    private function hasApplicationTables(): bool
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enquiries'");

        return (int) $statement->fetchColumn() > 0;
    }
}
