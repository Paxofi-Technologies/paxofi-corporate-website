<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use Paxofi\CorporateWebsite\Http\Middleware\RequestIdMiddleware;
use Paxofi\CorporateWebsite\Http\RequestAttributes;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\TestCase;

final class RequestIdMiddlewareTest extends TestCase
{
    use HttpRequests;

    public function testGeneratesIdWhenAbsent(): void
    {
        $seen = null;
        $response = (new RequestIdMiddleware())->process(self::request('GET', '/'), self::handlerReturning(self::ok(), $seen));

        $id = $seen?->attributes()[RequestAttributes::REQUEST_ID] ?? null;
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $id);
        self::assertSame($id, $response->header('x-request-id'));
    }

    public function testPropagatesWellFormedInboundId(): void
    {
        $response = (new RequestIdMiddleware())->process(self::request('GET', '/', ['x-request-id' => 'edge-1234abcd']), self::handlerReturning(self::ok()));

        self::assertSame('edge-1234abcd', $response->header('x-request-id'));
    }

    public function testReplacesMalformedInboundId(): void
    {
        $response = (new RequestIdMiddleware())->process(self::request('GET', '/', ['x-request-id' => "bad id\r\nx: y"]), self::handlerReturning(self::ok()));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $response->header('x-request-id'));
    }
}
