<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Careers\TalentPoolService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** /api/v1/admin/talent-pool (D-029). Reading needs recruitment.read; withdrawing consent needs recruitment.manage. */
final class AdminTalentPoolController
{
    public function __construct(private readonly TalentPoolService $pool, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::RECRUITMENT_READ);
        $result = $this->pool->list($request->query());

        return ApiResponse::success($request, $result['items'], $result['meta'], 200, ['cache-control' => 'no-store']);
    }

    public function withdraw(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::RECRUITMENT_MANAGE);
        $this->pool->withdraw(RequestContexts::routeParameter($request, 'id'), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['withdrawn' => true]);
    }
}
