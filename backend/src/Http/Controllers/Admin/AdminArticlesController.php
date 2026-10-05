<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Articles\ArticleEditor;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/articles[/{id}...] (D-021). Writing needs content.edit;
 * publishing, showing/hiding and deleting need content.publish.
 */
final class AdminArticlesController
{
    public function __construct(private readonly ArticleEditor $editor, private readonly AdminGuard $guard)
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

        return ApiResponse::success($request, $this->editor->get(self::id($request)));
    }

    public function create(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->create(RequestBody::parse($request), $staff, RequestContexts::from($request)), [], 201);
    }

    public function saveDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->saveDraft(self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function discardDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->discardDraft(self::id($request), $staff, RequestContexts::from($request)));
    }

    public function publish(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->publish(self::id($request), $staff, RequestContexts::from($request)));
    }

    public function visibility(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->setVisible(self::id($request), RequestBody::parse($request)['visible'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function delete(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);
        $this->editor->delete(self::id($request), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['deleted' => true]);
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }
}
