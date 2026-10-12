<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Careers\CertificateService;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * /api/v1/admin/certificates/{id}... (D-029). Downloading needs
 * recruitment.read; revoking or deleting needs certificates.manage
 * (administrators). Issuing is done from the application.
 */
final class AdminCertificatesController
{
    public function __construct(private readonly CertificateService $certificates, private readonly AdminGuard $guard)
    {
    }

    /** The certificate PDF, always as a download. */
    public function pdf(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::RECRUITMENT_READ);
        $file = $this->certificates->pdf(RequestContexts::routeParameter($request, 'id'));

        return new Response(200, [
            'content-type' => 'application/pdf',
            'content-length' => (string) strlen($file['bytes']),
            'content-disposition' => 'attachment; filename="' . $file['filename'] . '"',
            'cache-control' => 'no-store',
        ], $file['bytes']);
    }

    public function revoke(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CERTIFICATES_MANAGE);

        return ApiResponse::success($request, $this->certificates->revoke(RequestContexts::routeParameter($request, 'id'), RequestBody::parse($request)['reason'] ?? null, $staff, RequestContexts::from($request)));
    }

    public function delete(HttpRequest $request): HttpResponse
    {
        $staff = $this->guard->require($request, Permission::CERTIFICATES_MANAGE);
        $this->certificates->delete(RequestContexts::routeParameter($request, 'id'), $staff, RequestContexts::from($request));

        return ApiResponse::success($request, ['deleted' => true]);
    }
}
