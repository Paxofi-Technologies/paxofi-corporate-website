<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Paxofi\CorporateWebsite\Application;
use Paxofi\CorporateWebsite\Http\Request;
use Paxofi\CorporateWebsite\Http\Response;
use Paxofi\CorporateWebsite\Http\Router;

$app = new Application();
$router = new Router();

$router->get('/api/v1/health', static function (Request $request) use ($app): Response {
    return new Response(
        success: true,
        data: ['application' => $app->name(), 'status' => 'ok'],
        requestId: bin2hex(random_bytes(16)),
    );
});

$router->get('/api/v1/readiness', static function (Request $request) use ($app): Response {
    return new Response(
        success: true,
        data: ['application' => $app->name(), 'status' => 'ready'],
        requestId: bin2hex(random_bytes(16)),
    );
});

$request = Request::fromGlobals();
$response = $router->dispatch($request);

http_response_code($response->status);
header('Content-Type: application/json; charset=utf-8');
header('X-Request-ID: ' . $response->requestId);

echo $response->toJson();
