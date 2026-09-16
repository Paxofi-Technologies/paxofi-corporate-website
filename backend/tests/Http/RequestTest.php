<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Http;

use Paxofi\CorporateWebsite\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testRequestNormalizesMethodAndPreservesInput(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/v1/enquiries',
            query: ['page' => '1'],
            body: ['name' => 'Example'],
            headers: ['Content-Type' => 'application/json'],
        );

        self::assertSame('POST', $request->method);
        self::assertSame('/api/v1/enquiries', $request->path);
        self::assertSame(['page' => '1'], $request->query);
        self::assertSame(['name' => 'Example'], $request->body);
    }
}
