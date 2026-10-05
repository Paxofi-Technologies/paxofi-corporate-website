<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Media\MediaLibrary;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/resources: documents an administrator listed on the Resources page (D-023), newest first. */
final class ResourcesController implements Controller
{
    public function __construct(private readonly MediaLibrary $library)
    {
    }

    public function __invoke(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->library->resources(), ['categories' => MediaLibrary::RESOURCE_CATEGORIES]);
    }
}
