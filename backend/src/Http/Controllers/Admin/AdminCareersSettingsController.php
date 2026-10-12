<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Careers\CareersSettingsService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/careers-settings (D-028): the day applications open, the last
 * day for Cohort 1 and the staff test code. These change the live careers
 * site, so they need content.publish (administrators).
 */
final class AdminCareersSettingsController
{
    public function __construct(private readonly CareersSettingsService $settings, private readonly AdminGuard $guard)
    {
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->settings->get());
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->settings->update(RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function newTestCode(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->settings->newTestCode($staff, RequestContexts::from($request)), [], 201, ['Cache-Control' => 'no-store']);
    }

    public function removeTestCode(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->settings->removeTestCode($staff, RequestContexts::from($request)));
    }
}
