<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Backup;

use DateTimeImmutable;
use PDO;
use PharData;
use RuntimeException;

/**
 * Nightly backup (decision D-016): the whole database as a gzipped SQL file
 * (restorable with phpMyAdmin → Import) and the media library as a .tar.gz,
 * written to BACKUP_PATH outside the website folders. Copies older than
 * $keepDays days are deleted. Pure PHP: no mysqldump needed on the host.
 */
final class BackupRunner
{
    private const ROWS_PER_INSERT = 200;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $backupPath,
        private readonly ?string $mediaPath,
        private readonly int $keepDays = 14,
    ) {
    }

    /** @return array{database: string, media: ?string, deleted: int, tables: int} */
    public function run(DateTimeImmutable $now): array
    {
        $directory = rtrim($this->backupPath, '/');
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("The backup folder {$directory} does not exist and could not be created.");
        }
        if (!is_writable($directory)) {
            throw new RuntimeException("The backup folder {$directory} is not writable.");
        }
        $stamp = $now->format('Ymd-His');

        [$database, $tables] = $this->dumpDatabase("{$directory}/paxofi-database-{$stamp}.sql.gz");
        $media = $this->archiveMedia("{$directory}/paxofi-media-{$stamp}.tar");

        return ['database' => $database, 'media' => $media, 'deleted' => $this->prune($directory, $now), 'tables' => $tables];
    }

    /** @return array{0: string, 1: int} */
    private function dumpDatabase(string $file): array
    {
        $partial = $file . '.part';
        $out = gzopen($partial, 'wb6');
        if ($out === false) {
            throw new RuntimeException("Could not write {$partial}.");
        }
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $write = static function (string $text) use ($out): void {
            if (gzwrite($out, $text) === false) {
                throw new RuntimeException('Writing the database backup failed (disk full?).');
            }
        };
        $write("-- Paxofi corporate website database backup\n-- Database: {$database}\n-- Created (UTC): " . gmdate('Y-m-d H:i:s') . "\n-- Restore: phpMyAdmin → select an EMPTY database → Import this file (see RUNBOOKS RB-18).\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tables = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        $this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
        try {
            foreach ($tables as $table) {
                $table = (string) $table;
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $create = $this->pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
                $write("DROP TABLE IF EXISTS {$quoted};\n" . $create[1] . ";\n\n");

                $rows = $this->pdo->query("SELECT * FROM {$quoted}", PDO::FETCH_ASSOC);
                $batch = [];
                $columns = null;
                foreach ($rows as $row) {
                    $columns ??= '(' . implode(', ', array_map(static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                    $batch[] = '(' . implode(', ', array_map($this->literal(...), $row)) . ')';
                    if (count($batch) >= self::ROWS_PER_INSERT) {
                        $write("INSERT INTO {$quoted} {$columns} VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $write("INSERT INTO {$quoted} {$columns} VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                $write("\n");
            }
        } finally {
            $this->pdo->exec('COMMIT');
        }
        $write("SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        gzclose($out);
        if (!rename($partial, $file)) {
            throw new RuntimeException("Could not finish {$file}.");
        }
        @chmod($file, 0600);

        return [$file, count($tables)];
    }

    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Quoted by the connection itself (utf8mb4), so it restores as the same value in any column type.
        return (string) $this->pdo->quote((string) $value);
    }

    private function archiveMedia(string $tar): ?string
    {
        if ($this->mediaPath === null || !is_dir($this->mediaPath)) {
            return null;
        }
        @unlink($tar);
        @unlink($tar . '.gz');
        $archive = new PharData($tar);
        $archive->buildFromDirectory($this->mediaPath);
        $archive->compress(\Phar::GZ);
        unset($archive);
        @unlink($tar);
        @chmod($tar . '.gz', 0600);

        return $tar . '.gz';
    }

    private function prune(string $directory, DateTimeImmutable $now): int
    {
        $deleted = 0;
        $cutoff = $now->getTimestamp() - $this->keepDays * 86400;
        foreach (glob($directory . '/paxofi-{database,media}-*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
