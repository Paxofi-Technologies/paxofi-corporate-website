<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Content\ContentRepository;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Pagination;

final class PdoContentRepository implements ContentRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findPublished(Pagination $pagination, ?string $contentType = null, ?string $slug = null): array
    {
        $where = "ci.lifecycle_state = 'published' AND ci.published_at IS NOT NULL AND ci.published_at <= CURRENT_TIMESTAMP";
        $parameters = [];
        if ($contentType !== null) {
            $where .= ' AND ci.content_type = :content_type';
            $parameters['content_type'] = $contentType;
        }
        if ($slug !== null) {
            $where .= ' AND ci.slug = :slug';
            $parameters['slug'] = $slug;
        }

        try {
            $count = $this->database->reader()->fetchAll("SELECT COUNT(*) AS total FROM content_items ci WHERE {$where}", $parameters);
            $rows = $this->database->reader()->fetchAll(
                sprintf(
                    'SELECT ci.slug, ci.title, ci.content_type, ci.published_at, cr.revision_no, cr.content_json
                     FROM content_items ci
                     LEFT JOIN content_revisions cr ON cr.content_item_id = ci.id
                       AND cr.revision_no = (SELECT MAX(r2.revision_no) FROM content_revisions r2 WHERE r2.content_item_id = ci.id)
                     WHERE %s
                     ORDER BY ci.published_at DESC, ci.slug ASC
                     LIMIT %d OFFSET %d',
                    $where,
                    $pagination->perPage,
                    $pagination->offset(),
                ),
                $parameters,
            );
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }

        $items = [];
        foreach ($rows as $row) {
            $body = is_string($row['content_json'] ?? null) ? json_decode($row['content_json'], true) : null;
            $items[] = [
                'slug' => $row['slug'],
                'title' => $row['title'],
                'type' => $row['content_type'],
                'revision' => $row['revision_no'] === null ? null : (int) $row['revision_no'],
                'body' => is_array($body) ? $body : null,
                'published_at' => is_string($row['published_at']) ? str_replace(' ', 'T', $row['published_at']) . 'Z' : null,
            ];
        }

        return ['items' => $items, 'total' => (int) ($count[0]['total'] ?? 0)];
    }
}
