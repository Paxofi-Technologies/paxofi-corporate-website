<?php

declare(strict_types=1);

use Paxofi\CorporateWebsite\Http\Response;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$response = new Response(
    success: true,
    data: ['ok' => true],
    requestId: 'test-request',
);

$payload = json_decode($response->toJson(), true, 512, JSON_THROW_ON_ERROR);

assert($payload['success'] === true);
assert($payload['data']['ok'] === true);
assert($payload['request_id'] === 'test-request');
