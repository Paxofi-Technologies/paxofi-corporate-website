<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Media\MediaKind;
use Paxofi\CorporateWebsite\Application\Media\MediaRepository;

/** media_assets (001, extended by 010). */
final class PdoMediaRepository implements MediaRepository
{
    use GuardedQueries;

    private const SELECT = "SELECT m.id, m.kind, m.filename, m.media_type, m.storage_reference, m.size_bytes, m.width, m.height,
                                   m.alt_text, m.title, m.sha256, m.created_at, u.display_name AS uploader_name
                            FROM media_assets m LEFT JOIN users u ON u.id = m.uploaded_by
                            WHERE m.lifecycle_state = 'active'";

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function insert(array $row): void
    {
        $this->write(
            "INSERT INTO media_assets (id, kind, filename, media_type, storage_reference, lifecycle_state, size_bytes, width, height, alt_text, title, sha256, uploaded_by)
             VALUES (:id, :kind, :filename, :media_type, :storage_reference, 'active', :size_bytes, :width, :height, :alt_text, :title, :sha256, :uploaded_by)",
            $row,
        );
    }

    public function find(string $id): ?array
    {
        return $this->select(self::SELECT . ' AND m.id = :id', ['id' => $id])[0] ?? null;
    }

    public function all(?MediaKind $kind): array
    {
        return $kind === null
            ? $this->select(self::SELECT . ' ORDER BY m.created_at DESC, m.id LIMIT 500')
            : $this->select(self::SELECT . ' AND m.kind = :kind ORDER BY m.created_at DESC, m.id LIMIT 500', ['kind' => $kind->value]);
    }

    public function updateDetails(string $id, ?string $altText, ?string $title): void
    {
        $this->write('UPDATE media_assets SET alt_text = :alt_text, title = :title WHERE id = :id', ['id' => $id, 'alt_text' => $altText, 'title' => $title]);
    }

    public function delete(string $id): void
    {
        $this->write('DELETE FROM media_assets WHERE id = :id', ['id' => $id]);
    }

    public function usage(): array
    {
        $usage = [];
        $add = static function (mixed $mediaId, string $label) use (&$usage): void {
            if (is_string($mediaId) && preg_match('/^[0-9a-f-]{36}$/', $mediaId) === 1 && !in_array($label, $usage[$mediaId] ?? [], true)) {
                $usage[$mediaId][] = $label;
            }
        };

        $live = $this->select(
            "SELECT 'product' AS item_type, name, image_id, document_id FROM products WHERE image_id IS NOT NULL OR document_id IS NOT NULL
             UNION ALL
             SELECT 'service', name, image_id, document_id FROM services WHERE image_id IS NOT NULL OR document_id IS NOT NULL",
        );
        foreach ($live as $row) {
            $label = $row['name'] . ' (' . $row['item_type'] . ')';
            $add($row['image_id'], $label);
            $add($row['document_id'], $label);
        }

        $drafts = $this->select(
            "SELECT r.item_type, COALESCE(p.name, s.name) AS name,
                    JSON_UNQUOTE(JSON_EXTRACT(r.data, '$.image_id')) AS image_id,
                    JSON_UNQUOTE(JSON_EXTRACT(r.data, '$.document_id')) AS document_id
             FROM catalog_revisions r
             LEFT JOIN products p ON r.item_type = 'product' AND p.id = r.item_id
             LEFT JOIN services s ON r.item_type = 'service' AND s.id = r.item_id
             WHERE r.state = 'draft'",
        );
        foreach ($drafts as $row) {
            $label = ($row['name'] ?? 'an item') . ' (' . $row['item_type'] . ' draft)';
            $add($row['image_id'], $label);
            $add($row['document_id'], $label);
        }

        return $usage;
    }
}
