<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopyRepository;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/** page_revisions (migration 012, D-015): one draft per page and every published version. */
final class PdoPageCopyRepository implements PageCopyRepository
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function published(string $page): ?array
    {
        $row = $this->select(
            "SELECT data, created_at FROM page_revisions WHERE page = :page AND state = 'published' ORDER BY created_at DESC, id DESC LIMIT 1",
            ['page' => $page],
        )[0] ?? null;

        return $row === null ? null : ['data' => self::json($row['data']), 'created_at' => self::time($row['created_at'])];
    }

    public function draft(string $page): ?array
    {
        $row = $this->select(
            "SELECT r.data, r.created_at, u.display_name AS author_name FROM page_revisions r LEFT JOIN users u ON u.id = r.author_id
             WHERE r.page = :page AND r.state = 'draft' ORDER BY r.created_at DESC LIMIT 1",
            ['page' => $page],
        )[0] ?? null;

        return $row === null ? null : ['data' => self::json($row['data']), 'created_at' => self::time($row['created_at']), 'author_name' => $row['author_name'] ?? null];
    }

    public function saveDraft(string $page, array $data, string $authorId): void
    {
        $this->deleteDraft($page);
        $this->insert($page, 'draft', $data, $authorId);
    }

    public function deleteDraft(string $page): void
    {
        $this->write("DELETE FROM page_revisions WHERE page = :page AND state = 'draft'", ['page' => $page]);
    }

    public function addPublished(string $page, array $data, string $authorId): void
    {
        $this->insert($page, 'published', $data, $authorId);
    }

    public function history(string $page, int $limit = 20): array
    {
        $rows = $this->select(
            sprintf(
                "SELECT r.id, r.created_at, u.display_name AS author_name FROM page_revisions r LEFT JOIN users u ON u.id = r.author_id
                 WHERE r.page = :page AND r.state = 'published' ORDER BY r.created_at DESC, r.id DESC LIMIT %d",
                max(1, min(100, $limit)),
            ),
            ['page' => $page],
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'created_at' => self::time($row['created_at']),
            'author_name' => $row['author_name'] ?? null,
        ], $rows);
    }

    public function revision(string $page, string $revisionId): ?array
    {
        $row = $this->select(
            "SELECT data FROM page_revisions WHERE id = :id AND page = :page AND state = 'published'",
            ['id' => $revisionId, 'page' => $page],
        )[0] ?? null;

        return $row === null ? null : self::json($row['data']);
    }

    public function overview(): array
    {
        $overview = [];
        foreach ($this->select(
            "SELECT page, MAX(CASE WHEN state = 'published' THEN created_at END) AS published_at, MAX(state = 'draft') AS has_draft
             FROM page_revisions GROUP BY page",
        ) as $row) {
            $overview[(string) $row['page']] = [
                'published_at' => $row['published_at'] === null ? null : self::time($row['published_at']),
                'has_draft' => (bool) $row['has_draft'],
            ];
        }

        return $overview;
    }

    /** @param array<string, string> $data */
    private function insert(string $page, string $state, array $data, string $authorId): void
    {
        $this->write(
            'INSERT INTO page_revisions (id, page, state, data, author_id) VALUES (:id, :page, :state, :data, :author)',
            ['id' => Uuid::v4(), 'page' => $page, 'state' => $state, 'data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'author' => $authorId],
        );
    }

    /** Microsecond timestamps are kept for ordering; staff see whole seconds. */
    private static function time(mixed $value): string
    {
        return substr((string) $value, 0, 19);
    }

    /** @return array<string, mixed> */
    private static function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
