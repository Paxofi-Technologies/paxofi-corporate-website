<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Catalog;

/** Public, published collections exposed by the corporate website API. */
enum CatalogType: string
{
    case Products = 'products';
    case Services = 'services';
    case Careers = 'careers';
}
