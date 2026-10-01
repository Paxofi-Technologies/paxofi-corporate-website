<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Middleware;

use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpMiddleware;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Http\RequestAttributes;

/**
 * Assigns a correlation id to every request. A well-formed inbound
 * X-Request-Id (e.g. from a proxy) is propagated; anything else is replaced
 * so untrusted values never reach logs or the database.
 */
final class RequestIdMiddleware implements HttpMiddleware
{
    public function process(HttpRequest $request, HttpHandler $handler): HttpResponse
    {
        $inbound = $request->header('x-request-id');
        $requestId = $inbound !== null && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $inbound) === 1
            ? $inbound
            : bin2hex(random_bytes(16));

        $response = $handler->handle($request->withAttribute(RequestAttributes::REQUEST_ID, $requestId));

        return $response->withHeader('x-request-id', $requestId);
    }
}
