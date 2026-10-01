<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Content;

use Paxofi\CorporateWebsite\Application\Catalog\SlugFilter;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Pagination;

final class ContentService
{
    public function __construct(private readonly ContentRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listPublished(array $query): array
    {
        $pagination = Pagination::fromQuery($query);
        $slug = SlugFilter::fromQuery($query);

        $type = $query['type'] ?? null;
        if ($type === '') {
            $type = null;
        }
        if ($type !== null && (!is_string($type) || preg_match('/^[a-z][a-z0-9_]{0,79}$/', $type) !== 1)) {
            throw new ValidationFailed(['type' => 'Must be a lowercase content type identifier.'], 'Invalid query parameters.');
        }

        $result = $this->repository->findPublished($pagination, $type, $slug);

        return [
            'items' => $result['items'],
            'meta' => ['published' => true] + $pagination->meta($result['total']),
        ];
    }
}
