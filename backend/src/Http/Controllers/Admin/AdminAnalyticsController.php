<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Analytics\Analytics;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/admin/analytics?days=7|30|90 (analytics.read, D-014). */
final class AdminAnalyticsController
{
    public function __construct(private readonly Analytics $analytics, private readonly AdminGuard $guard)
    {
    }

    public function report(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::ANALYTICS_READ);

        return ApiResponse::success($request, $this->analytics->report((int) ($request->query()['days'] ?? 30)));
    }
}
