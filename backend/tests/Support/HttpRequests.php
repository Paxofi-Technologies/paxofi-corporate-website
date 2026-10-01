<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Support;

use Paxofi\Core\Contracts\HttpHandler;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Request;
use Paxofi\Core\Http\Response;

trait HttpRequests
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $query
     */
    private static function request(string $method, string $uri, array $headers = [], string $body = '', array $query = [], ?string $clientIp = '203.0.113.10'): HttpRequest
    {
        if ($query === [] && str_contains($uri, '?')) {
            parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        }

        return new Request($method, $uri, $headers, $body, $query, ['client_ip' => $clientIp]);
    }

    /** @param array<string, mixed> $payload */
    private static function jsonPost(string $uri, array $payload, array $headers = []): HttpRequest
    {
        return self::request('POST', $uri, ['content-type' => 'application/json'] + $headers, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private static function decode(HttpResponse $response): array
    {
        $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private static function handlerReturning(HttpResponse|\Throwable $outcome, ?HttpRequest &$seen = null): HttpHandler
    {
        return new class ($outcome, $seen) implements HttpHandler {
            public function __construct(private HttpResponse|\Throwable $outcome, private ?HttpRequest &$seen)
            {
            }

            public function handle(HttpRequest $request): HttpResponse
            {
                $this->seen = $request;
                if ($this->outcome instanceof \Throwable) {
                    throw $this->outcome;
                }

                return $this->outcome;
            }
        };
    }

    private static function ok(): HttpResponse
    {
        return new Response(200, ['content-type' => 'application/json'], '{}');
    }
}
