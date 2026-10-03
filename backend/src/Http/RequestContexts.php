<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\CorporateWebsite\Application\RequestContext;

final class RequestContexts
{
    public static function from(HttpRequest $request): RequestContext
    {
        $clientIp = $request->attributes()[RequestAttributes::CLIENT_IP] ?? null;

        return new RequestContext(
            requestId: ApiResponse::requestId($request) ?? '',
            clientIp: is_string($clientIp) ? $clientIp : null,
            userAgent: $request->header('user-agent'),
        );
    }

    public static function routeParameter(HttpRequest $request, string $name): string
    {
        $parameters = $request->attributes()[RequestAttributes::ROUTE_PARAMETERS] ?? [];

        return is_array($parameters) && is_string($parameters[$name] ?? null) ? $parameters[$name] : '';
    }
}
