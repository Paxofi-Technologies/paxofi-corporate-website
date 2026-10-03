<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopyEditor;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/pages[/{page}[/draft|/publish|/revisions/{revision}/restore]] (D-015).
 * Every action needs content.edit; publishing also needs content.publish.
 */
final class AdminPagesController
{
    public function __construct(private readonly PageCopyEditor $editor, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->list());
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->get(self::page($request)));
    }

    public function saveDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->saveDraft(self::page($request), RequestBody::parse($request)['fields'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function discardDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->discardDraft(self::page($request), $staff, RequestContexts::from($request)));
    }

    public function publish(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->editor->publish(self::page($request), $staff, RequestContexts::from($request)));
    }

    public function restore(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->restore(self::page($request), RequestContexts::routeParameter($request, 'revision'), $staff, RequestContexts::from($request)));
    }

    private static function page(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'page');
    }
}
