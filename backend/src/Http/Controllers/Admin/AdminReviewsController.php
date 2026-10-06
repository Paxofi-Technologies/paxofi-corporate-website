<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Freshness\ContentReviews;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** /api/v1/admin/reviews[/{type}/{key}] (D-025): review dates for live content; content.edit. */
final class AdminReviewsController
{
    public function __construct(private readonly ContentReviews $reviews, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);
        $items = $this->reviews->list();

        return ApiResponse::success($request, $items, [
            'counts' => ContentReviews::counts($items),
            'today' => $this->reviews->today()->format('Y-m-d'),
            'intervals' => ContentReviews::INTERVALS,
            'due_soon_days' => ContentReviews::DUE_SOON_DAYS,
        ]);
    }

    public function update(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->reviews->update(
            RequestContexts::routeParameter($request, 'type'),
            RequestContexts::routeParameter($request, 'key'),
            RequestBody::parse($request),
            $staff,
            RequestContexts::from($request),
        ));
    }
}
