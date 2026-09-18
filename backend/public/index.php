<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Http/JsonResponse.php';

use Paxofi\CorporateWebsite\Http\JsonResponse;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestId = bin2hex(random_bytes(8));

if ($path === '/api/v1/health' && $method === 'GET') {
    JsonResponse::send([
        'success' => true,
        'data' => ['status' => 'ok', 'service' => 'paxofi-corporate-website-api'],
        'meta' => [],
        'request_id' => $requestId,
    ]);
}

if ($path === '/api/v1/readiness' && $method === 'GET') {
    JsonResponse::send([
        'success' => true,
        'data' => ['status' => 'ready', 'checks' => ['application' => true, 'database' => 'deferred-to-runtime']],
        'meta' => [],
        'request_id' => $requestId,
    ]);
}

if ($path === '/api/v1/navigation' && $method === 'GET') {
    JsonResponse::send([
        'success' => true,
        'data' => [
            ['label' => 'About', 'href' => '/about'],
            ['label' => 'Services', 'href' => '/services'],
            ['label' => 'Products', 'href' => '/products'],
            ['label' => 'Careers', 'href' => '/careers'],
            ['label' => 'Contact', 'href' => '/contact'],
        ],
        'meta' => [],
        'request_id' => $requestId,
    ]);
}

$catalog = [
    'products' => [
        ['slug' => 'paxofi-pay', 'name' => 'Paxofi Pay', 'summary' => 'Digital payments infrastructure focused on reliability, transaction certainty, transparency, recovery and trust.'],
        ['slug' => 'paxofi-core-framework', 'name' => 'Paxofi Core Framework', 'summary' => 'An independent PHP application framework for maintainable internal, client, SaaS and API systems.'],
    ],
    'services' => [
        ['slug' => 'software-engineering', 'name' => 'Software Engineering', 'summary' => 'Web platforms, APIs and business applications built for reliability.'],
        ['slug' => 'digital-products', 'name' => 'Digital Products', 'summary' => 'Product strategy and production-ready customer experiences.'],
        ['slug' => 'technology-infrastructure', 'name' => 'Technology Infrastructure', 'summary' => 'Practical architecture, deployment and operational foundations.'],
        ['slug' => 'digital-growth', 'name' => 'Digital Growth', 'summary' => 'Web, digital marketing and technology-enabled business growth.'],
    ],
    'careers' => [],
];

foreach (['products', 'services', 'careers'] as $resource) {
    if ($path === '/api/v1/' . $resource && $method === 'GET') {
        JsonResponse::send([
            'success' => true,
            'data' => $catalog[$resource],
            'meta' => ['published' => true],
            'request_id' => $requestId,
        ]);
    }
}

if ($path === '/api/v1/content' && $method === 'GET') {
    JsonResponse::send([
        'success' => true,
        'data' => [],
        'meta' => ['published' => true],
        'request_id' => $requestId,
    ]);
}

if (preg_match('#^/api/v1/forms/([^/]+)/submit$#', $path, $matches) === 1 && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    if (empty($input['name']) || empty($input['email']) || empty($input['message'])) {
        JsonResponse::send([
            'success' => false,
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Name, email and message are required.'],
            'request_id' => $requestId,
        ], 422);
    }

    JsonResponse::send([
        'success' => true,
        'data' => ['accepted' => true, 'form_key' => $matches[1]],
        'meta' => [],
        'request_id' => $requestId,
    ], 202);
}

JsonResponse::send([
    'success' => false,
    'error' => ['code' => 'NOT_FOUND', 'message' => 'Resource not found.'],
    'request_id' => $requestId,
], 404);
