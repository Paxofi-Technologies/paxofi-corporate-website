<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Response;

/**
 * Builds the API's JSON envelope on top of the PCF Response:
 *   success: {success: true,  data, meta, request_id}
 *   failure: {success: false, error: {code, message, details?}, request_id}
 */
final class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, string> $headers
     */
    public static function success(HttpRequest $request, mixed $data, array $meta = [], int $status = 200, array $headers = []): HttpResponse
    {
        return self::json($status, [
            'success' => true,
            'data' => $data,
            'meta' => (object) $meta,
            'request_id' => self::requestId($request),
        ], $headers);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public static function error(HttpRequest $request, int $status, string $code, string $message, array $details = [], array $headers = []): HttpResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::json($status, [
            'success' => false,
            'error' => $error,
            'request_id' => self::requestId($request),
        ], $headers);
    }

    public static function requestId(HttpRequest $request): ?string
    {
        $id = $request->attributes()[RequestAttributes::REQUEST_ID] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private static function json(int $status, array $body, array $headers): HttpResponse
    {
        return new Response(
            $status,
            ['content-type' => 'application/json; charset=utf-8'] + $headers,
            json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }
}
