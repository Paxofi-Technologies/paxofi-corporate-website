<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogContent;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogEditorRepository;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogKind;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * Live rows in `products` / `services` / `industries`; drafts and history in
 * `catalog_revisions` (state 'draft' — at most one per item — 'published' or
 * 'created'). Products also store a status, industries a description and
 * related items (D-022).
 */
final class PdoCatalogEditorRepository implements CatalogEditorRepository
{
    use GuardedQueries;

    private const COLUMNS = 'id, slug, name, label, icon, image_id, document_id, summary, points, sort_order, lifecycle_state, published_at, updated_at';
    private const EXTRA = ['products' => ['status'], 'services' => [], 'industries' => ['description', 'related']];

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function all(CatalogKind $kind): array
    {
        $table = self::table($kind);
        $status = $kind === CatalogKind::Products ? 't.status, ' : '';

        return array_map(self::decode(...), $this->select(
            "SELECT t.id, t.slug, t.name, t.label, {$status}t.icon, t.summary, t.points, t.sort_order, t.lifecycle_state, t.published_at, t.updated_at,
                    EXISTS(SELECT 1 FROM catalog_revisions r WHERE r.item_type = :type AND r.item_id = t.id AND r.state = 'draft') AS has_draft
             FROM {$table} t ORDER BY t.sort_order, t.name",
            ['type' => $kind->singular()],
        ));
    }

    public function find(CatalogKind $kind, string $id): ?array
    {
        $row = $this->select('SELECT ' . self::selectColumns($kind) . ' FROM ' . self::table($kind) . ' WHERE id = :id', ['id' => $id])[0] ?? null;

        return $row === null ? null : self::decode($row);
    }

    public function slugExists(CatalogKind $kind, string $slug): bool
    {
        return $this->select('SELECT 1 FROM ' . self::table($kind) . ' WHERE slug = :slug', ['slug' => $slug]) !== [];
    }

    public function insert(CatalogKind $kind, string $slug, CatalogContent $content): string
    {
        $id = Uuid::v4();
        $values = self::columns($kind, $content);
        $names = implode(', ', array_keys($values));
        $this->write(
            'INSERT INTO ' . self::table($kind) . " (id, slug, {$names}, lifecycle_state, published_at)
             VALUES (:id, :slug, :" . implode(', :', array_keys($values)) . ", 'draft', NULL)",
            ['id' => $id, 'slug' => $slug] + $values,
        );

        return $id;
    }

    public function updateLive(CatalogKind $kind, string $id, CatalogContent $content): void
    {
        $values = self::columns($kind, $content);
        $this->write(
            'UPDATE ' . self::table($kind) . ' SET ' . implode(', ', array_map(static fn (string $c): string => "{$c} = :{$c}", array_keys($values))) . ' WHERE id = :id',
            ['id' => $id] + $values,
        );
    }

    public function setVisible(CatalogKind $kind, string $id, bool $visible): void
    {
        $this->write(
            $visible
                ? 'UPDATE ' . self::table($kind) . " SET lifecycle_state = 'published', published_at = COALESCE(published_at, UTC_TIMESTAMP()) WHERE id = :id"
                : 'UPDATE ' . self::table($kind) . " SET lifecycle_state = 'draft' WHERE id = :id",
            ['id' => $id],
        );
    }

    public function draft(CatalogKind $kind, string $id): ?array
    {
        $row = $this->select(
            "SELECT r.id, r.data, r.created_at, u.display_name AS author_name FROM catalog_revisions r LEFT JOIN users u ON u.id = r.author_id
             WHERE r.item_type = :type AND r.item_id = :id AND r.state = 'draft' ORDER BY r.created_at DESC LIMIT 1",
            ['type' => $kind->singular(), 'id' => $id],
        )[0] ?? null;

        return $row === null ? null : [
            'id' => (string) $row['id'],
            'data' => self::json($row['data']),
            'author_name' => $row['author_name'] ?? null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    public function saveDraft(CatalogKind $kind, string $id, CatalogContent $content, string $authorId): void
    {
        $this->deleteDraft($kind, $id);
        $this->addRevision($kind, $id, 'draft', $content, $authorId);
    }

    public function deleteDraft(CatalogKind $kind, string $id): void
    {
        $this->write("DELETE FROM catalog_revisions WHERE item_type = :type AND item_id = :id AND state = 'draft'", ['type' => $kind->singular(), 'id' => $id]);
    }

    public function markDraftPublished(CatalogKind $kind, string $id, string $authorId): void
    {
        $this->write(
            "UPDATE catalog_revisions SET state = 'published', author_id = :author, created_at = CURRENT_TIMESTAMP
             WHERE item_type = :type AND item_id = :id AND state = 'draft'",
            ['type' => $kind->singular(), 'id' => $id, 'author' => $authorId],
        );
    }

    public function addRevision(CatalogKind $kind, string $id, string $state, CatalogContent $content, string $authorId): void
    {
        $this->write(
            'INSERT INTO catalog_revisions (id, item_type, item_id, state, data, author_id) VALUES (:rid, :type, :id, :state, :data, :author)',
            [
                'rid' => Uuid::v4(),
                'type' => $kind->singular(),
                'id' => $id,
                'state' => $state,
                'data' => json_encode($content->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'author' => $authorId,
            ],
        );
    }

    public function revisions(CatalogKind $kind, string $id, int $limit = 20): array
    {
        $rows = $this->select(
            sprintf(
                "SELECT r.id, r.state, r.data, r.created_at, u.display_name AS author_name FROM catalog_revisions r LEFT JOIN users u ON u.id = r.author_id
                 WHERE r.item_type = :type AND r.item_id = :id AND r.state <> 'draft' ORDER BY r.created_at DESC, r.id DESC LIMIT %d",
                max(1, min(100, $limit)),
            ),
            ['type' => $kind->singular(), 'id' => $id],
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'state' => (string) $row['state'],
            'data' => self::json($row['data']),
            'author_name' => $row['author_name'] ?? null,
            'created_at' => (string) $row['created_at'],
        ], $rows);
    }

    public function revision(CatalogKind $kind, string $id, string $revisionId): ?array
    {
        $row = $this->select(
            'SELECT id, state, data FROM catalog_revisions WHERE id = :rid AND item_type = :type AND item_id = :id',
            ['rid' => $revisionId, 'type' => $kind->singular(), 'id' => $id],
        )[0] ?? null;

        return $row === null ? null : ['id' => (string) $row['id'], 'state' => (string) $row['state'], 'data' => self::json($row['data'])];
    }

    /** Table names are fixed per kind, never user input. */
    private static function table(CatalogKind $kind): string
    {
        return match ($kind) {
            CatalogKind::Products => 'products',
            CatalogKind::Services => 'services',
            CatalogKind::Industries => 'industries',
        };
    }

    private static function selectColumns(CatalogKind $kind): string
    {
        return implode(', ', array_merge([self::COLUMNS], self::EXTRA[$kind->value]));
    }

    /**
     * Column => value for the live row; column names are fixed here, never user input.
     *
     * @return array<string, mixed>
     */
    private static function columns(CatalogKind $kind, CatalogContent $content): array
    {
        $extra = [
            'status' => $content->status,
            'description' => $content->description,
            'related' => json_encode($content->related, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];

        return array_intersect_key($extra, array_flip(self::EXTRA[$kind->value])) + [
            'name' => $content->name,
            'label' => $content->label,
            'icon' => $content->icon,
            'image_id' => $content->imageId,
            'document_id' => $content->documentId,
            'summary' => $content->summary,
            'points' => json_encode($content->points, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'sort_order' => $content->sortOrder,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function decode(array $row): array
    {
        $row['points'] = self::json($row['points'] ?? null);
        if (array_key_exists('related', $row)) {
            $row['related'] = self::json($row['related']);
        }
        $row['has_draft'] = (bool) ($row['has_draft'] ?? false);

        return $row;
    }

    /** @return array<mixed> */
    private static function json(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
