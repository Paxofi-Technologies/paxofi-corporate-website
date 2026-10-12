<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Mail\MailDiagnostics;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** /api/v1/admin/mail (D-031): administrators only (users.manage). */
final class AdminMailController
{
    public function __construct(private readonly MailDiagnostics $mail, private readonly AdminGuard $guard)
    {
    }

    public function status(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->mail->status(), [], 200, ['cache-control' => 'no-store']);
    }

    public function save(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->mail->save(RequestBody::parse($request), $staff, RequestContexts::from($request)), [], 200, ['cache-control' => 'no-store']);
    }

    public function test(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->mail->sendTest($staff, RequestContexts::from($request)), [], 200, ['cache-control' => 'no-store']);
    }

    public function sendQueued(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->mail->sendQueued($staff, RequestContexts::from($request)), [], 200, ['cache-control' => 'no-store']);
    }
}
