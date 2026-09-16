<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Http;

use Paxofi\CorporateWebsite\Http\Request;
use Paxofi\CorporateWebsite\Http\Response;
use Paxofi\CorporateWebsite\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRegisteredGetRouteDispatches(): void
    {
        $router = new Router();
        $router->get('/health', static fn (Request $request): Response => new Response(
            success: true,
            data: ['status' => 'ok'],
            requestId: 'router-test',
        ));

        $response = $router->dispatch(new Request('GET', '/health'));
        $payload = json_decode($response->toJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->status);
        self::assertTrue($payload['success']);
        self::assertSame('ok', $payload['data']['status']);
        self::assertSame('router-test', $payload['request_id']);
    }

    public function testMissingRouteReturnsNotFound(): void
    {
        $response = (new Router())->dispatch(new Request('GET', '/missing'));

        self::assertSame(404, $response->status);
        self::assertFalse($response->success);
    }
}
