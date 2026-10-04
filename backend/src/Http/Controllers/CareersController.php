<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Careers\CareersService;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Http\ApiResponse;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestContexts;

/**
 * careers.paxofi.com (D-018): published roles, CV upload and applications.
 *
 * A CV is sent as the request body (Content-Type: application/octet-stream,
 * filename in the query). That content type makes browsers ask first (CORS
 * preflight), so only the website's own addresses can upload.
 */
final class CareersController
{
    public function __construct(private readonly CareersService $careers)
    {
    }

    public function roles(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->careers->roles());
    }

    public function role(HttpRequest $request): HttpResponse
    {
        return ApiResponse::success($request, $this->careers->role(RequestContexts::routeParameter($request, 'slug')));
    }

    public function uploadCv(HttpRequest $request): HttpResponse
    {
        if (strtolower(trim(explode(';', $request->header('content-type') ?? '')[0])) !== 'application/octet-stream') {
            throw self::cvError('Send the CV file itself (application/octet-stream).');
        }
        $body = $request->body();
        if ($body === '') {
            // PHP drops bodies larger than post_max_size before the API sees them.
            throw self::cvError((int) ($request->header('content-length') ?? '0') > 0 ? 'Your CV can be up to 5 MB.' : 'Choose your CV file.');
        }
        $query = $request->query();
        $filename = is_string($query['filename'] ?? null) ? mb_substr($query['filename'], 0, 255) : 'CV';

        return ApiResponse::success($request, $this->careers->uploadCv($body, $filename, RequestContexts::from($request)), [], 201);
    }

    public function apply(HttpRequest $request): HttpResponse
    {
        $receipt = $this->careers->apply(RequestContexts::routeParameter($request, 'slug'), RequestBody::parse($request), RequestContexts::from($request));

        return ApiResponse::success($request, $receipt, [], 201);
    }

    private static function cvError(string $message): ValidationFailed
    {
        return new ValidationFailed(['cv' => $message], $message);
    }
}
