<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Http\Request;

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

        return new Request(
            is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET',
            is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/',
            $headers,
            $body,
            $stringQuery,
            [RequestAttributes::CLIENT_IP => is_string($clientIp) && filter_var($clientIp, FILTER_VALIDATE_IP) !== false ? $clientIp : null],
        );
    }

    public static function readBody(): string
    {
        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return '';
        }
        // Read one byte past the limit so oversized bodies are detectable downstream.
        $body = stream_get_contents($stream, self::MAX_BODY_BYTES + 1);
        fclose($stream);

        return $body === false ? '' : $body;
    }
}
