<?php

declare(strict_types=1);

use Paxofi\CorporateWebsite\Http\Request;
use Paxofi\CorporateWebsite\Http\Response;
use Paxofi\CorporateWebsite\Http\Router;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$router = new Router();
$router->get('/health', static fn (Request $request): Response => new Response(
    success: true,
    data: ['status' => 'ok'],
    requestId: 'router-test',
));

$response = $router->dispatch(new Request('GET', '/health'));
$payload = json_decode($response->toJson(), true, 512, JSON_THROW_ON_ERROR);

assert($response->status === 200);
assert($payload['success'] === true);
assert($payload['data']['status'] === 'ok');
assert($payload['request_id'] === 'router-test');

$missing = $router->dispatch(new Request('GET', '/missing'));
assert($missing->status === 404);
