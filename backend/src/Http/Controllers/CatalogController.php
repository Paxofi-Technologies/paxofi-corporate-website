<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogService;
use Paxofi\CorporateWebsite\Application\Catalog\CatalogType;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/{products|services|careers} */
final class CatalogController implements Controller
{
    public function __construct(private readonly CatalogService $catalog, private readonly CatalogType $type)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        $result = $this->catalog->listPublished($this->type, $request->query());

        return ApiResponse::success($request, $result['items'], $result['meta'], 200, ['cache-control' => 'public, max-age=60']);
    }
}
