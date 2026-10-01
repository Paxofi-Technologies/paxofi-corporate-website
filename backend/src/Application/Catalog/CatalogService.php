<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Catalog;

use Paxofi\CorporateWebsite\Application\Pagination;

final class CatalogService
{
    public function __construct(private readonly CatalogRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $query raw query-string parameters
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listPublished(CatalogType $type, array $query): array
    {
        $pagination = Pagination::fromQuery($query);
        $slug = SlugFilter::fromQuery($query);

        $result = $this->repository->findPublished($type, $pagination, $slug);

        return [
            'items' => $result['items'],
            'meta' => ['published' => true] + $pagination->meta($result['total']),
        ];
    }
}
