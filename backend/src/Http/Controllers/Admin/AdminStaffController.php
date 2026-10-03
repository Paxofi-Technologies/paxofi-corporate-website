<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Admin\Role;
use Paxofi\CorporateWebsite\Application\Admin\StaffAdminService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** GET|POST /api/v1/admin/users, PATCH /api/v1/admin/users/{id} */
final class AdminStaffController
{
    public function __construct(private readonly StaffAdminService $staff, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::USERS_MANAGE);
        $roles = array_map(static fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()], Role::cases());

        return ApiResponse::success($request, $this->staff->list(), ['roles' => $roles]);
    }

    public function create(HttpRequest $request): HttpResponse
    {
        $actor = $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->staff->create(RequestBody::parse($request), $actor, RequestContexts::from($request)), [], 201);
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $actor = $this->guard->require($request, Permission::USERS_MANAGE);

        return ApiResponse::success($request, $this->staff->update(
            RequestContexts::routeParameter($request, 'id'),
            RequestBody::parse($request),
            $actor,
            RequestContexts::from($request),
        ));
    }
}
