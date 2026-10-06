<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Freshness\Redirects;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/redirects: old addresses and where the website sends them (D-025). Read by the website every minute. */
final class RedirectsController implements Controller
{
    public function __construct(private readonly Redirects $redirects)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->redirects->map());
    }
}
