<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Articles\ArticleService;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** GET /api/v1/articles and /api/v1/articles/{slug}: published News & Insights (D-021). */
final class ArticlesController
{
    public function __construct(private readonly ArticleService $articles)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $result = $this->articles->list($request->query());

        return ApiResponse::success($request, $result['items'], $result['meta']);
    }

    public function show(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->articles->get(RequestContexts::routeParameter($request, 'slug')));
    }
}
