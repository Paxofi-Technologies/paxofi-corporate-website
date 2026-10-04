<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Careers\CareerRoleEditor;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** /api/v1/admin/career-roles[/{id}[/state]] (D-018): needs careers.edit. */
final class AdminCareerRolesController
{
    public function __construct(private readonly CareerRoleEditor $editor, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CAREERS_EDIT);

        return ApiResponse::success($request, $this->editor->list());
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CAREERS_EDIT);

        return ApiResponse::success($request, $this->editor->get(self::id($request)));
    }

    public function create(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CAREERS_EDIT);

        return ApiResponse::success($request, $this->editor->create(RequestBody::parse($request), $staff, RequestContexts::from($request)), [], 201);
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CAREERS_EDIT);

        return ApiResponse::success($request, $this->editor->update(self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function state(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CAREERS_EDIT);

        return ApiResponse::success($request, $this->editor->setState(self::id($request), RequestBody::parse($request)['state'] ?? null, $staff, RequestContexts::from($request)));
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }
}
