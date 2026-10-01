<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Catalog;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

final class SlugFilter
{
    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query, string $parameter = 'slug'): ?string
    {
        $slug = $query[$parameter] ?? null;
        if ($slug === null || $slug === '') {
            return null;
        }

        if (!is_string($slug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) > 180) {
            throw new ValidationFailed([$parameter => 'Must be a lowercase slug (letters, digits and hyphens).'], 'Invalid query parameters.');
        }

        return $slug;
    }
}
