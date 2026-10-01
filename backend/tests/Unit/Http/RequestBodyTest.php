<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Http\RequestBody;
use Paxofi\CorporateWebsite\Http\RequestFactory;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\TestCase;

final class RequestBodyTest extends TestCase
{
    use HttpRequests;

    public function testParsesJsonObject(): void
    {
        self::assertSame(['name' => 'Ada'], RequestBody::parse(self::request('POST', '/', ['content-type' => 'application/json'], '{"name":"Ada"}')));
    }

    public function testParsesFormEncoded(): void
    {
        self::assertSame(['name' => 'Ada Lovelace'], RequestBody::parse(self::request('POST', '/', ['content-type' => 'application/x-www-form-urlencoded'], 'name=Ada+Lovelace')));
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(ValidationFailed::class);
        RequestBody::parse(self::request('POST', '/', ['content-type' => 'application/json'], '{"name":'));
    }

    public function testRejectsJsonList(): void
    {
        $this->expectException(ValidationFailed::class);
        RequestBody::parse(self::request('POST', '/', ['content-type' => 'application/json'], '["a"]'));
    }

    public function testRejectsOversizedBody(): void
    {
        $this->expectException(ValidationFailed::class);
        RequestBody::parse(self::request('POST', '/', ['content-type' => 'application/json'], str_repeat(' ', RequestFactory::MAX_BODY_BYTES + 1)));
    }

    public function testHeadIsServedAsGet(): void
    {
        self::assertSame('GET', RequestFactory::fromGlobals(['REQUEST_METHOD' => 'HEAD', 'REQUEST_URI' => '/api/v1/health'], [], '')->method());
    }

    public function testRequestFactoryMapsServerVariables(): void
    {
        $request = RequestFactory::fromGlobals(
            ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/api/v1/x?a=1', 'HTTP_X_REQUEST_ID' => 'abc', 'CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => 'not-an-ip'],
            ['a' => '1', 'b' => ['nested']],
            '{}',
        );

        self::assertSame('POST', $request->method());
        self::assertSame('abc', $request->header('x-request-id'));
        self::assertSame('application/json', $request->header('content-type'));
        self::assertSame(['a' => '1'], $request->query(), 'non-string query values are dropped');
        self::assertNull($request->attributes()['client_ip']);
    }
}
