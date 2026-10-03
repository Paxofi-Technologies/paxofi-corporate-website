<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Catalog\CatalogRepository;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogType;
use Paxofi\CorporateWebsite\Application\Pagination;

final class PdoCatalogRepository implements CatalogRepository
{
    use PublishedQuery;

    /** Table and public column projection per catalog type. Identifiers are fixed, never user input. */
    private const SOURCES = [
        'products' => ['table' => 'products', 'columns' => 'slug, name, label, icon, summary, points, sort_order, published_at', 'order' => 'sort_order ASC, name ASC'],
        'services' => ['table' => 'services', 'columns' => 'slug, name, label, icon, summary, points, sort_order, published_at', 'order' => 'sort_order ASC, name ASC'],
        'careers' => ['table' => 'career_opportunities', 'columns' => 'slug, title, description, published_at', 'order' => 'published_at DESC, slug ASC'],
    ];

    public function __construct(private readonly Database $database)
    {
    }

    public function findPublished(CatalogType $type, Pagination $pagination, ?string $slug = null): array
    {
        $source = self::SOURCES[$type->value];

        return $this->publishedPage($this->database, $source['table'], $source['columns'], $pagination, $slug === null ? [] : ['slug' => $slug], $source['order']);
    }
}
