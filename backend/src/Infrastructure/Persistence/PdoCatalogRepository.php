<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogRepository;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogType;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Media\MediaPath;
use Paxofi\CorporateWebsite\Application\Media\MediaRules;
use Paxofi\CorporateWebsite\Application\Pagination;

final class PdoCatalogRepository implements CatalogRepository
{
    use PublishedQuery;

    /** Table and public column projection per catalog type. Identifiers are fixed, never user input. */
    private const SOURCES = [
        'products' => ['table' => 'products', 'columns' => 'slug, name, label, status, icon, image_id, document_id, summary, points, sort_order, published_at', 'order' => 'sort_order ASC, name ASC'],
        'services' => ['table' => 'services', 'columns' => 'slug, name, label, icon, image_id, document_id, summary, points, sort_order, published_at', 'order' => 'sort_order ASC, name ASC'],
        'industries' => ['table' => 'industries', 'columns' => 'slug, name, label, icon, image_id, document_id, summary, description, points, related, sort_order, published_at', 'order' => 'sort_order ASC, name ASC'],
        'careers' => ['table' => 'career_opportunities', 'columns' => 'slug, title, description, published_at', 'order' => 'published_at DESC, slug ASC'],
    ];

    public function __construct(private readonly Database $database)
    {
    }

    public function findPublished(CatalogType $type, Pagination $pagination, ?string $slug = null): array
    {
        $source = self::SOURCES[$type->value];
        $page = $this->publishedPage($this->database, $source['table'], $source['columns'], $pagination, $slug === null ? [] : ['slug' => $slug], $source['order']);

        if ($type === CatalogType::Careers) {
            return $page;
        }
        $items = $this->withMedia($page['items']);

        return ['items' => $type === CatalogType::Industries ? $this->withRelated($items) : $items, 'total' => $page['total']];
    }

    /**
     * Replaces each industry's related references with the published products
     * and services they name (D-022), in the order staff chose; hidden or
     * deleted ones are left out.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withRelated(array $items): array
    {
        $published = [];
        try {
            foreach (['product' => 'products', 'service' => 'services'] as $type => $table) {
                $status = $type === 'product' ? 'status' : 'NULL AS status';
                $rows = $this->database->reader()->fetchAll(
                    "SELECT slug, name, icon, summary, {$status} FROM {$table}
                     WHERE lifecycle_state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP",
                );
                foreach ($rows as $row) {
                    $published[$type . ':' . $row['slug']] = [
                        'type' => $type,
                        'slug' => (string) $row['slug'],
                        'name' => (string) $row['name'],
                        'icon' => $row['icon'] ?? null,
                        'summary' => (string) $row['summary'],
                        'status' => $row['status'] ?? null,
                    ];
                }
            }
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }

        return array_map(static function (array $item) use ($published): array {
            $references = is_string($item['related'] ?? null) ? json_decode($item['related'], true) : null;
            $item['related'] = array_values(array_filter(array_map(
                static fn (mixed $reference): ?array => is_string($reference) ? ($published[$reference] ?? null) : null,
                is_array($references) ? $references : [],
            )));

            return $item;
        }, $items);
    }

    /**
     * Replaces image_id/document_id with what the website needs to show them (D-012):
     * image {path, alt, width, height} and document {path, title, format, size_bytes}.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function withMedia(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_merge(array_column($items, 'image_id'), array_column($items, 'document_id')), 'is_string')));
        $media = [];
        if ($ids !== []) {
            $parameters = [];
            foreach ($ids as $i => $id) {
                $parameters['id' . $i] = $id;
            }
            try {
                $rows = $this->database->reader()->fetchAll(
                    "SELECT id, kind, filename, media_type, size_bytes, width, height, alt_text, title FROM media_assets
                     WHERE lifecycle_state = 'active' AND id IN (:" . implode(', :', array_keys($parameters)) . ')',
                    $parameters,
                );
            } catch (PersistenceException $exception) {
                throw new DependencyUnavailable(previous: $exception);
            }
            foreach ($rows as $row) {
                $media[(string) $row['id']] = $row;
            }
        }

        return array_map(static function (array $item) use ($media): array {
            $image = $media[$item['image_id'] ?? ''] ?? null;
            $document = $media[$item['document_id'] ?? ''] ?? null;
            unset($item['image_id'], $item['document_id']);
            $item['image'] = $image === null || $image['kind'] !== 'image' ? null : [
                'path' => MediaPath::for((string) $image['id'], (string) $image['filename']),
                'alt' => (string) $image['alt_text'],
                'width' => (int) $image['width'],
                'height' => (int) $image['height'],
            ];
            $item['document'] = $document === null || $document['kind'] !== 'document' ? null : [
                'path' => MediaPath::for((string) $document['id'], (string) $document['filename']),
                'title' => (string) $document['title'],
                'format' => MediaRules::formatLabel((string) $document['media_type']),
                'size_bytes' => (int) $document['size_bytes'],
            ];

            return $item;
        }, $items);
    }
}
