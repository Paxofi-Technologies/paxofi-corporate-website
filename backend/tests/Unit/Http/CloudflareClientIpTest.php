<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use Paxofi\CorporateWebsite\Http\CloudflareClientIp;
use Paxofi\CorporateWebsite\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

final class CloudflareClientIpTest extends TestCase
{
    public function testVisitorAddressIsTakenFromCloudflareConnections(): void
    {
        self::assertSame('102.89.40.7', CloudflareClientIp::resolve(['REMOTE_ADDR' => '172.70.1.20', 'HTTP_CF_CONNECTING_IP' => '102.89.40.7']));
        self::assertSame('2c0f:f5c0:1::7', CloudflareClientIp::resolve(['REMOTE_ADDR' => '2606:4700:10::ac43:1', 'HTTP_CF_CONNECTING_IP' => ' 2c0f:f5c0:1::7 ']));
    }

    public function testHeaderIsIgnoredFromAnyoneElse(): void
    {
        self::assertSame('102.89.40.7', CloudflareClientIp::resolve(['REMOTE_ADDR' => '102.89.40.7', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']), 'a direct request cannot choose its address');
        self::assertSame('162.254.39.103', CloudflareClientIp::resolve(['REMOTE_ADDR' => '162.254.39.103', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']));
    }

    public function testInvalidHeaderFallsBackToTheConnection(): void
    {
        self::assertSame('172.70.1.20', CloudflareClientIp::resolve(['REMOTE_ADDR' => '172.70.1.20', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4, 5.6.7.8']));
        self::assertSame('172.70.1.20', CloudflareClientIp::resolve(['REMOTE_ADDR' => '172.70.1.20']));
        self::assertNull(CloudflareClientIp::resolve(['REMOTE_ADDR' => 'not-an-ip', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']));
        self::assertNull(CloudflareClientIp::resolve([]));
    }

    public function testRangeEdges(): void
    {
        self::assertTrue(CloudflareClientIp::isCloudflare('104.16.0.0'));
        self::assertTrue(CloudflareClientIp::isCloudflare('104.23.255.255'), 'last address of 104.16.0.0/13');
        self::assertTrue(CloudflareClientIp::isCloudflare('104.24.0.0'), '104.24.0.0/14 is its own range');
        self::assertFalse(CloudflareClientIp::isCloudflare('104.28.0.0'));
        self::assertFalse(CloudflareClientIp::isCloudflare('104.15.255.255'));
        self::assertTrue(CloudflareClientIp::isCloudflare('173.245.63.255'));
        self::assertFalse(CloudflareClientIp::isCloudflare('173.245.64.0'));
        self::assertTrue(CloudflareClientIp::isCloudflare('2a06:98c7:ffff::1'), 'inside 2a06:98c0::/29');
        self::assertFalse(CloudflareClientIp::isCloudflare('2a06:98c8::1'));
        self::assertFalse(CloudflareClientIp::isCloudflare('::ffff:104.16.0.1'), 'mapped addresses are not matched against IPv4 ranges');
        self::assertFalse(CloudflareClientIp::isCloudflare('127.0.0.1'));
    }

    public function testRequestFactoryUsesTheVisitorAddress(): void
    {
        $request = RequestFactory::fromGlobals(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '162.158.1.1', 'HTTP_CF_CONNECTING_IP' => '41.58.1.2'], [], '');
        self::assertSame('41.58.1.2', $request->attributes()['client_ip']);
    }
}
