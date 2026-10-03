<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use Paxofi\CorporateWebsite\Http\Middleware\CorsMiddleware;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    use HttpRequests;

    private const PREFLIGHT = ['origin' => 'https://paxofi.com', 'access-control-request-method' => 'POST', 'access-control-request-headers' => 'content-type'];

    public function testPreflightFromAllowedOriginShortCircuits(): void
    {
        $seen = null;
        $response = (new CorsMiddleware(['https://paxofi.com/']))->process(
            self::request('OPTIONS', '/api/v1/forms/contact/submit', self::PREFLIGHT),
            self::handlerReturning(self::ok(), $seen),
        );

        self::assertNull($seen, 'preflight must not reach the router');
        self::assertSame(204, $response->status());
        self::assertSame('https://paxofi.com', $response->header('access-control-allow-origin'));
        self::assertStringContainsString('POST', (string) $response->header('access-control-allow-methods'));
        self::assertStringContainsString('Content-Type', (string) $response->header('access-control-allow-headers'));
        // Staff session cookie (D-009): credentials only for exact, configured origins.
        self::assertSame('true', $response->header('access-control-allow-credentials'));
        self::assertStringContainsString('PATCH', (string) $response->header('access-control-allow-methods'));
        self::assertStringContainsString('DELETE', (string) $response->header('access-control-allow-methods'));
    }

    public function testPreflightFromUnknownOriginIsDenied(): void
    {
        $response = (new CorsMiddleware(['https://paxofi.com']))->process(
            self::request('OPTIONS', '/api/v1/forms/contact/submit', ['origin' => 'https://evil.example'] + self::PREFLIGHT),
            self::handlerReturning(self::ok()),
        );

        self::assertSame(403, $response->status());
        self::assertNull($response->header('access-control-allow-origin'));
        self::assertNull($response->header('access-control-allow-credentials'));
    }

    public function testSimpleRequestFromAllowedOriginGetsHeaders(): void
    {
        $response = (new CorsMiddleware(['https://paxofi.com']))->process(
            self::request('GET', '/api/v1/products', ['origin' => 'https://paxofi.com']),
            self::handlerReturning(self::ok()),
        );

        self::assertSame('https://paxofi.com', $response->header('access-control-allow-origin'));
        self::assertSame('Origin', $response->header('vary'));
    }

    public function testSimpleRequestFromUnknownOriginGetsNoAllowOrigin(): void
    {
        $response = (new CorsMiddleware([]))->process(
            self::request('GET', '/api/v1/products', ['origin' => 'https://evil.example']),
            self::handlerReturning(self::ok()),
        );

        self::assertNull($response->header('access-control-allow-origin'));
    }
}
