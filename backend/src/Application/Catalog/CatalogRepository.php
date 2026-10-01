<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Catalog;

use Paxofi\CorporateWebsite\Application\Pagination;

interface CatalogRepository
{
    /**
     * Published items only, newest publication first.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findPublished(CatalogType $type, Pagination $pagination, ?string $slug = null): array;
}
