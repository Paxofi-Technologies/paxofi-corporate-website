<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\Controller;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** Primary site navigation. Static in V1; mirrors the frontend header. */
final class NavigationController implements Controller
{
    public const ITEMS = [
        ['label' => 'About', 'href' => '/about'],
        ['label' => 'Services', 'href' => '/services'],
        ['label' => 'Products', 'href' => '/products'],
        ['label' => 'Careers', 'href' => '/careers'],
        ['label' => 'Contact', 'href' => '/contact'],
    ];

    public function __invoke(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, self::ITEMS, [], 200, ['cache-control' => 'public, max-age=300']);
    }
}
