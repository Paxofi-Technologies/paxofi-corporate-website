<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Http;

use Paxofi\CorporateWebsite\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testResponseSerializesCanonicalPayload(): void
    {
        $response = new Response(
            success: true,
            data: ['ok' => true],
            requestId: 'test-request',
        );

        $payload = json_decode($response->toJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertTrue($payload['data']['ok']);
        self::assertSame('test-request', $payload['request_id']);
    }
}
