<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogContent;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogEditor;
use Paxofi\CorporateWebsite\Application\Admin\Catalog\CatalogKind;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/catalog/{type}[/{id}[/draft|/publish|/visibility|/revisions/{revision}/restore]] (D-011).
 * Every action needs content.edit; publishing and visibility also need content.publish.
 */
final class AdminCatalogController
{
    public function __construct(private readonly CatalogEditor $editor, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->list(self::kind($request)), ['icons' => CatalogContent::ICONS]);
    }

    public function create(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->create(self::kind($request), RequestBody::parse($request), $staff, RequestContexts::from($request)), [], 201);
    }

    public function show(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->get(self::kind($request), self::id($request)), ['icons' => CatalogContent::ICONS]);
    }

    public function saveDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->saveDraft(self::kind($request), self::id($request), RequestBody::parse($request), $staff, RequestContexts::from($request)));
    }

    public function discardDraft(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->discardDraft(self::kind($request), self::id($request), $staff, RequestContexts::from($request)));
    }

    public function publish(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);

        return ApiResponse::success($request, $this->editor->publish(self::kind($request), self::id($request), $staff, RequestContexts::from($request)));
    }

    public function visibility(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_PUBLISH);
        $body = RequestBody::parse($request);

        return ApiResponse::success($request, $this->editor->setVisible(self::kind($request), self::id($request), $body['visible'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function restore(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CONTENT_EDIT);

        return ApiResponse::success($request, $this->editor->restore(
            self::kind($request),
            self::id($request),
            RequestContexts::routeParameter($request, 'revision'),
            $staff,
            RequestContexts::from($request),
        ));
    }

    private static function kind(HttpRequest $request): CatalogKind
    {
        return CatalogKind::tryFrom(RequestContexts::routeParameter($request, 'type')) ?? throw new ResourceNotFound('Unknown collection.');
    }

    private static function id(HttpRequest $request): string
    {
        return RequestContexts::routeParameter($request, 'id');
    }
}
