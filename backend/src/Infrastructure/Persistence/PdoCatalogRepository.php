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
        'products' => ['table' => 'products', 'columns' => 'slug, name, summary, published_at'],
        'services' => ['table' => 'services', 'columns' => 'slug, name, summary, published_at'],
        'careers' => ['table' => 'career_opportunities', 'columns' => 'slug, title, description, published_at'],
    ];

    public function __construct(private readonly Database $database)
    {
    }

    public function findPublished(CatalogType $type, Pagination $pagination, ?string $slug = null): array
    {
        $source = self::SOURCES[$type->value];

        return $this->publishedPage($this->database, $source['table'], $source['columns'], $pagination, $slug === null ? [] : ['slug' => $slug]);
    }
}
