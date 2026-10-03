<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\AdminEnquiryService;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** GET /api/v1/admin/enquiries, GET|PATCH /api/v1/admin/enquiries/{id} */
final class AdminEnquiryController
{
    public function __construct(private readonly AdminEnquiryService $enquiries, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::ENQUIRIES_READ);
        $result = $this->enquiries->list($request->query());

        return ApiResponse::success($request, $result['items'], $result['meta']);
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::ENQUIRIES_READ);

        return ApiResponse::success($request, $this->enquiries->get(RequestContexts::routeParameter($request, 'id'), $staff, RequestContexts::from($request)));
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::ENQUIRIES_UPDATE);

        return ApiResponse::success($request, $this->enquiries->update(
            RequestContexts::routeParameter($request, 'id'),
            RequestBody::parse($request),
            $staff,
            RequestContexts::from($request),
        ));
    }
}
