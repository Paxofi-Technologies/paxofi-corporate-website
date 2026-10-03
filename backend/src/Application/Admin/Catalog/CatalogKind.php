<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Catalog;

/** The catalogue collections staff can edit (D-011). */
enum CatalogKind: string
{
    case Products = 'products';
    case Services = 'services';

    /** Audit target type and revision item type. */
    public function singular(): string
    {
        return $this === self::Products ? 'product' : 'service';
    }
}
