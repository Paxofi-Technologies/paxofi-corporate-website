<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\TwoFactorService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * GET /api/v1/admin/account/two-factor, POST .../setup, .../enable,
 * .../recovery-codes, .../disable; POST /api/v1/admin/users/{id}/two-factor/reset (D-010)
 */
final class AdminTwoFactorController
{
    public function __construct(private readonly TwoFactorService $twoFactor, private readonly AdminGuard $guard)
    {
    }

    public function status(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->twoFactor->status($this->guard->require($request, duringEnrollment: true)));
    }

    public function setup(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->twoFactor->beginSetup($this->guard->require($request, duringEnrollment: true)));
    }

    public function enable(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, duringEnrollment: true);
        $codes = $this->twoFactor->enable($staff, RequestBody::parse($request), RequestContexts::from($request));

        return ApiResponse::success($request, ['enabled' => true, 'recovery_codes' => $codes]);
    }

    public function recoveryCodes(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, duringEnrollment: true);

        return ApiResponse::success($request, ['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($staff, RequestBody::parse($request), RequestContexts::from($request))]);
    }

    public function disable(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, duringEnrollment: true);
        $this->twoFactor->disable($staff, RequestBody::parse($request), RequestContexts::from($request));

        return ApiResponse::success($request, ['enabled' => false]);
    }

    public function reset(HttpRequest $request): HttpResponse
    {
        $actor = $this->guard->require($request, Permission::USERS_MANAGE);
        $user = $this->twoFactor->reset(RequestContexts::routeParameter($request, 'id'), $actor, RequestContexts::from($request));

        return ApiResponse::success($request, $user->toArray());
    }
}
