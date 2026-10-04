<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\PasswordResetService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** POST /api/v1/admin/password-reset and /api/v1/admin/password-reset/complete (D-016). */
final class AdminPasswordResetController
{
    public function __construct(private readonly PasswordResetService $resets, private readonly AdminGuard $guard)
    {
    }

    public function request(HttpRequest $request): HttpResponse
    {
        $this->guard->requireTrustedOrigin($request);
        $this->resets->request(RequestBody::parse($request), RequestContexts::from($request));

        // The same answer whether or not the address belongs to an account.
        return ApiResponse::success($request, ['requested' => true, 'available' => $this->resets->available()], [], 202);
    }

    public function complete(HttpRequest $request): HttpResponse
    {
        $this->guard->requireTrustedOrigin($request);
        $this->resets->complete(RequestBody::parse($request), RequestContexts::from($request));

        return ApiResponse::success($request, ['changed' => true]);
    }
}
