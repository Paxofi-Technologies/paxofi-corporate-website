<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Content\ContentService;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/content?type=&slug=&page=&per_page= */
final class ContentController implements Controller
{
    public function __construct(private readonly ContentService $content)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        $result = $this->content->listPublished($request->query());

        return ApiResponse::success($request, $result['items'], $result['meta'], 200, ['cache-control' => 'public, max-age=60']);
    }
}
