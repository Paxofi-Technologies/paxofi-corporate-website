<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopyEditor;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * GET /api/v1/pages/{page} (D-015): a page's published text. Fields not
 * published yet are left out; the website shows its built-in wording for them.
 */
final class PageCopyController implements Controller
{
    public function __construct(private readonly PageCopyEditor $pages)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->pages->live(RequestContexts::routeParameter($request, 'page')), [], 200, ['cache-control' => 'public, max-age=60']);
    }
}
