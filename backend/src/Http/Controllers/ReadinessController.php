<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Observability\HealthRegistry;
use Paxofi\Core\Observability\HealthStatus;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/**
 * Readiness: required dependencies are reachable. Reports only booleans per
 * check; diagnostic messages stay in server logs.
 */
final class ReadinessController implements Controller
{
    public function __construct(private readonly HealthRegistry $health)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        $checks = ['application' => true];
        $ready = true;
        foreach ($this->health->check() as $name => $result) {
            $healthy = $result->status !== HealthStatus::Unhealthy;
            $checks[$name] = $healthy;
            $ready = $ready && $healthy;
        }

        if (!$ready) {
            return ApiResponse::error($request, 503, 'NOT_READY', 'One or more dependencies are unavailable.', ['status' => 'not_ready', 'checks' => $checks]);
        }

        return ApiResponse::success($request, ['status' => 'ready', 'checks' => $checks]);
    }
}
