<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Middleware;

use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpMiddleware;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;

/** Baseline API response hardening, applied to every response including errors. */
final class SecurityHeadersMiddleware implements HttpMiddleware
{
    public function __construct(private readonly bool $enforceHsts = false)
    {
    }

    public function process(HttpRequest $request, HttpHandler $handler): HttpResponse
    {
        $response = $handler->handle($request)
            ->withHeader('x-content-type-options', 'nosniff')
            ->withHeader('x-frame-options', 'DENY')
            ->withHeader('referrer-policy', 'no-referrer')
            ->withHeader('content-security-policy', "default-src 'none'; frame-ancestors 'none'");

        // Media files (D-012) declare their own policy so the website may embed them.
        if ($response->header('cross-origin-resource-policy') === null) {
            $response = $response->withHeader('cross-origin-resource-policy', 'same-site');
        }
        if ($response->header('cache-control') === null) {
            $response = $response->withHeader('cache-control', 'no-store');
        }

        if ($this->enforceHsts) {
            $response = $response->withHeader('strict-transport-security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
