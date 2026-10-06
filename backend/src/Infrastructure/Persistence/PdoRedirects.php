<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Freshness\RedirectStore;

/** redirects (migration 020, D-025). */
final class PdoRedirects implements RedirectStore
{
    use GuardedQueries;

    private const SELECT = 'SELECT r.id, r.from_path, r.to_path, r.note, u.display_name AS created_by, r.created_at, r.updated_at
                            FROM redirects r LEFT JOIN users u ON u.id = r.created_by';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function all(): array
    {
        return array_map(self::row(...), $this->select(self::SELECT . ' ORDER BY r.from_path'));
    }

    public function find(string $id): ?array
    {
        $row = $this->select(self::SELECT . ' WHERE r.id = :id', ['id' => $id])[0] ?? null;

        return $row === null ? null : self::row($row);
    }

    public function create(string $id, string $from, string $to, ?string $note, string $createdBy): void
    {
        $this->write(
            'INSERT INTO redirects (id, from_path, to_path, note, created_by) VALUES (:id, :from_path, :to_path, :note, :created_by)',
            ['id' => $id, 'from_path' => $from, 'to_path' => $to, 'note' => $note, 'created_by' => $createdBy],
        );
    }

    public function update(string $id, string $from, string $to, ?string $note): void
    {
        $this->write(
            'UPDATE redirects SET from_path = :from_path, to_path = :to_path, note = :note WHERE id = :id',
            ['id' => $id, 'from_path' => $from, 'to_path' => $to, 'note' => $note],
        );
    }

    public function delete(string $id): void
    {
        $this->write('DELETE FROM redirects WHERE id = :id', ['id' => $id]);
    }

    public function livePaths(): array
    {
        $rows = $this->select(
            "SELECT CONCAT('/industries/', LOWER(slug)) AS path FROM industries
             WHERE lifecycle_state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP
             UNION ALL SELECT CONCAT('/insights/', LOWER(slug)) FROM articles
             WHERE state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP",
        );

        return array_map(static fn (array $row): string => (string) $row['path'], $rows);
    }

    /** @return array{id: string, from_path: string, to_path: string, note: string|null, created_by: string|null, created_at: string, updated_at: string} */
    private static function row(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'from_path' => (string) $row['from_path'],
            'to_path' => (string) $row['to_path'],
            'note' => $row['note'] === null ? null : (string) $row['note'],
            'created_by' => $row['created_by'] === null ? null : (string) $row['created_by'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
