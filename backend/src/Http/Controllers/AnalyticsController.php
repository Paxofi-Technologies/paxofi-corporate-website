<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Application\Analytics\Analytics;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Http\RequestAttributes;
use Paxofi\CorporateWebsite\Http\RequestBody;

/**
 * POST /api/v1/analytics/pageview (D-014). Sent by the website as a simple
 * request (text/plain, no cookies, no credentials). Always answers 204, so a
 * page never waits on or reacts to analytics.
 */
final class AnalyticsController implements Controller
{
    public function __construct(private readonly Analytics $analytics)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        try {
            $input = RequestBody::parse($request);
        } catch (ValidationFailed) {
            return new Response(204);
        }
        $clientIp = $request->attributes()[RequestAttributes::CLIENT_IP] ?? null;
        $optedOut = trim((string) $request->header('dnt')) === '1' || trim((string) $request->header('sec-gpc')) === '1';
        $this->analytics->record($input, is_string($clientIp) ? $clientIp : null, $request->header('user-agent'), $optedOut);

        return new Response(204);
    }
}
