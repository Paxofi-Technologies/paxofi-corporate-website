<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Paxofi\CorporateWebsite\Application;

$app = new Application();

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'data' => ['application' => $app->name()],
    'meta' => [],
    'request_id' => bin2hex(random_bytes(16)),
], JSON_THROW_ON_ERROR);
