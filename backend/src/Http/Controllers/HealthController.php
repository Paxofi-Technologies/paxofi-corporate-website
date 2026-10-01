<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** Liveness: the PHP process can serve requests. Never touches dependencies. */
final class HealthController implements Controller
{
    public function __invoke(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, ['status' => 'ok', 'service' => 'paxofi-corporate-website-api']);
    }
}
