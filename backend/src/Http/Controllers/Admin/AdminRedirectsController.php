<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Freshness\RedirectRules;
use Paxofi\CorporateWebsite\Application\Freshness\Redirects;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/redirects[/{id}] (D-025). Listing needs content.edit; adding,
 * changing and deleting change the live site, so they need content.publish.
 */
final class AdminRedirectsController
{
    public function __construct(private readonly Redirects $redirects, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->redirects->list(), ['max' => RedirectRules::MAX_REDIRECTS]);
    }

    public function create(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->redirects->create(RequestBody::parse($request), $staff, RequestContexts::from($request)), [], 201);
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->redirects->update(self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function delete(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);
        $this->redirects->delete(self::id($request), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['deleted' => true]);
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }
}
