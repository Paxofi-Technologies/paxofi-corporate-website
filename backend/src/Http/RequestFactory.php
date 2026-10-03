<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Http\Request;
use Paxofi\CorporateWebsite\Application\Media\MediaRules;

/** Builds an immutable PCF request from PHP superglobals (SAPI boundary). */
final class RequestFactory
{
    public const MAX_BODY_BYTES = 65536;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     */
    public static function fromGlobals(array $server, array $query, string $body): HttpRequest
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[str_replace('_', '-', strtolower($key))] = $value;
            }
        }

        $stringQuery = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $stringQuery[$key] = $value;
            }
        }

        $clientIp = $server['REMOTE_ADDR'] ?? null;
        $method = is_string($server['REQUEST_METHOD'] ?? null) ? strtoupper($server['REQUEST_METHOD']) : 'GET';

        return new Request(
            // HEAD is answered as GET; the SAPI emitter drops the body (RFC 9110 §9.3.2).
            $method === 'HEAD' ? 'GET' : $method,
            is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/',
            $headers,
            $body,
            $stringQuery,
            [RequestAttributes::CLIENT_IP => is_string($clientIp) && filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : null],
        );
    }

    /** The media upload route (D-012) is the only one whose body is a file. */
    public const UPLOAD_PATH = '/api/v1/admin/media';

    /**
     * How much of the request body to read: 64 KB, or the largest media file
     * on the upload route.
     *
     * @param array<string, mixed> $server
     */
    public static function bodyLimit(array $server): int
    {
        $path = is_string($server['REQUEST_URI'] ?? null) ? (string) parse_url($server['REQUEST_URI'], PHP_URL_PATH) : '';
        $isUpload = ($server['REQUEST_METHOD'] ?? '') === 'POST' && rtrim($path, '/') === self::UPLOAD_PATH;

        return $isUpload ? MediaRules::UPLOAD_MAX_BYTES : self::MAX_BODY_BYTES;
    }

    public static function readBody(int $limit = self::MAX_BODY_BYTES): string
    {
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return '';
        }
        // Read one byte past the limit so oversized bodies are detectable downstream.
        $body = stream_get_contents($stream, $limit + 1);
        fclose($stream);

        return $body === false ? '' : $body;
    }
}
