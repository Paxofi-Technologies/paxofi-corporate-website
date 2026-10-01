<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Middleware;

use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpMiddleware;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/**
 * Cross-origin access for the public website frontend (e.g. paxofi.com
 * calling api.paxofi.com). Only explicitly configured origins are allowed;
 * credentials are never allowed.
 */
final class CorsMiddleware implements HttpMiddleware
{
    private const ALLOWED_METHODS = 'GET, POST, OPTIONS';
    private const ALLOWED_HEADERS = 'Accept, Content-Type, X-Request-Id';
    private const MAX_AGE_SECONDS = '600';

    /** @var array<string, true> */
    private readonly array $allowedOrigins;

    /** @param list<string> $allowedOrigins exact origins, e.g. "https://paxofi.com" */
    public function __construct(array $allowedOrigins)
    {
        $normalized = [];
        foreach ($allowedOrigins as $origin) {
            $origin = rtrim(strtolower(trim($origin)), '/');
            if ($origin !== '') {
                $normalized[$origin] = true;
            }
        }
        $this->allowedOrigins = $normalized;
    }

    public function process(HttpRequest $request, HttpHandler $handler): HttpResponse
    {
        $origin = $request->header('origin');
        $allowed = $origin !== null && isset($this->allowedOrigins[strtolower($origin)]);
        $isPreflight = $request->method() === 'OPTIONS' && $request->header('access-control-request-method') !== null;

        if ($isPreflight) {
            if (!$allowed) {
                return ApiResponse::error($request, 403, 'CORS_ORIGIN_DENIED', 'Origin is not allowed.');
            }

            return $this->withCorsHeaders(new Response(204), $origin)
                ->withHeader('access-control-allow-methods', self::ALLOWED_METHODS)
                ->withHeader('access-control-allow-headers', self::ALLOWED_HEADERS)
                ->withHeader('access-control-max-age', self::MAX_AGE_SECONDS);
        }

        $response = $handler->handle($request);

        return $allowed ? $this->withCorsHeaders($response, $origin) : $response->withHeader('vary', 'Origin');
    }

    private function withCorsHeaders(HttpResponse $response, string $origin): HttpResponse
    {
        return $response
            ->withHeader('access-control-allow-origin', $origin)
            ->withHeader('access-control-expose-headers', 'X-Request-Id, Retry-After')
            ->withHeader('vary', 'Origin');
    }
}
