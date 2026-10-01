<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Content;

use Paxofi\CorporateWebsite\Application\Pagination;

interface ContentRepository
{
    /**
     * Published content items with their latest revision body.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function findPublished(Pagination $pagination, ?string $contentType = null, ?string $slug = null): array;
}
