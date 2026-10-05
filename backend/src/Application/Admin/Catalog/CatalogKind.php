<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Catalog;

/** The catalogue collections staff can edit (D-011; industries D-022). */
enum CatalogKind: string
{
    case Products = 'products';
    case Services = 'services';
    case Industries = 'industries';

    /** Audit target type and revision item type. */
    public function singular(): string
    {
        return match ($this) {
            self::Products => 'product',
            self::Services => 'service',
            self::Industries => 'industry',
        };
    }
}
