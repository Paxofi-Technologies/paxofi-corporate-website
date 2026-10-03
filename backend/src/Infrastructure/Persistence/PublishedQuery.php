<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Pagination;

/** Shared "published and visible now" paging query. */
trait PublishedQuery
{
    /**
     * @param array<string, string> $equals column => value (columns are trusted identifiers)
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private function publishedPage(Database $database, string $table, string $columns, Pagination $pagination, array $equals = [], string $order = 'published_at DESC, slug ASC'): array
    {
        $where = "lifecycle_state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP";
        foreach (array_keys($equals) as $column) {
            $where .= sprintf(' AND %1$s = :%1$s', $column);
        }

        try {
            $count = $database->reader()->fetchAll("SELECT COUNT(*) AS total FROM {$table} WHERE {$where}", $equals);
            $rows = $database->reader()->fetchAll(
                // LIMIT/OFFSET are validated integers; PDO cannot bind them portably with native prepares.
                sprintf('SELECT %s FROM %s WHERE %s ORDER BY %s LIMIT %d OFFSET %d', $columns, $table, $where, $order, $pagination->perPage, $pagination->offset()),
                $equals,
            );
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }

        return ['items' => array_map(self::formatRow(...), $rows), 'total' => (int) ($count[0]['total'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function formatRow(array $row): array
    {
        if (isset($row['published_at']) && is_string($row['published_at'])) {
            $row['published_at'] = str_replace(' ', 'T', $row['published_at']) . 'Z';
        }
        if (array_key_exists('points', $row)) {
            $points = is_string($row['points']) ? json_decode($row['points'], true) : null;
            $row['points'] = is_array($points) ? array_values(array_filter($points, 'is_string')) : [];
        }
        if (array_key_exists('sort_order', $row)) {
            $row['sort_order'] = (int) $row['sort_order'];
        }

        return $row;
    }
}
