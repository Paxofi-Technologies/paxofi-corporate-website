<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Careers\CertificateService;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/** careers.paxofi.com/verify (D-029): checks a certificate by its code. Never cached. */
final class CertificateVerificationController
{
    public function __construct(private readonly CertificateService $certificates)
    {
    }

    public function verify(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->certificates->verify(RequestContexts::routeParameter($request, 'code')), [], 200, ['cache-control' => 'no-store']);
    }
}
