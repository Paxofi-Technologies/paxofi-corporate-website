<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/Http/JsonResponse.php';
require dirname(__DIR__) . '/src/Database/Connection.php';

use Paxofi\CorporateWebsite\Database\Connection;
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
    try {
        $db = Connection::make();
        $db->query('SELECT 1');
        JsonResponse::send([
            'success' => true,
            'data' => ['status' => 'ready', 'checks' => ['application' => true, 'database' => true]],
            'meta' => [],
            'request_id' => $requestId,
        ]);
    } catch (Throwable $e) {
        JsonResponse::send([
            'success' => false,
            'data' => ['status' => 'not_ready', 'checks' => ['application' => true, 'database' => false]],
            'error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'Database readiness check failed.'],
            'request_id' => $requestId,
        ], 503);
    }
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
    $formKey = strtolower(trim($matches[1]));
    if ($formKey !== 'contact') {
        JsonResponse::send([
            'success' => false,
            'error' => ['code' => 'FORM_NOT_FOUND', 'message' => 'Form is not available.'],
            'request_id' => $requestId,
        ], 404);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $company = trim((string) ($input['company'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));

    if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        JsonResponse::send([
            'success' => false,
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'A valid name, email and message are required.'],
            'request_id' => $requestId,
        ], 422);
    }

    if (strlen($name) > 160 || strlen($email) > 255 || strlen($company) > 255 || strlen($message) > 10000) {
        JsonResponse::send([
            'success' => false,
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'One or more fields exceed the allowed length.'],
            'request_id' => $requestId,
        ], 422);
    }

    try {
        $db = Connection::make();

        // Application-level anti-abuse guard. This is intentionally conservative and
        // complements (rather than replaces) an edge/distributed rate limiter.
        $sourceIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $rateStmt = $db->prepare(
            'SELECT COUNT(*) FROM enquiries
             WHERE created_at >= (CURRENT_TIMESTAMP - INTERVAL 10 MINUTE)
               AND ((:source_ip IS NOT NULL AND source_ip = :source_ip_check)
                    OR email = :email)'
        );
        $rateStmt->execute([
            'source_ip' => $sourceIp,
            'source_ip_check' => $sourceIp,
            'email' => $email,
        ]);
        if ((int) $rateStmt->fetchColumn() >= 5) {
            JsonResponse::send([
                'success' => false,
                'error' => ['code' => 'RATE_LIMITED', 'message' => 'Too many enquiries. Please try again later.'],
                'request_id' => $requestId,
            ], 429);
        }

        $id = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
        $stmt = $db->prepare(
            'INSERT INTO enquiries (id, name, email, company, message, status, source_ip, user_agent, request_id)
             VALUES (:id, :name, :email, :company, :message, :status, :source_ip, :user_agent, :request_id)'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'company' => $company !== '' ? $company : null,
            'message' => $message,
            'status' => 'new',
            'source_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
            'request_id' => $requestId,
        ]);
    } catch (Throwable $e) {
        JsonResponse::send([
            'success' => false,
            'error' => ['code' => 'PERSISTENCE_ERROR', 'message' => 'The enquiry could not be recorded.'],
            'request_id' => $requestId,
        ], 503);
    }

    JsonResponse::send([
        'success' => true,
        'data' => ['accepted' => true, 'form_key' => $formKey],
        'meta' => [],
        'request_id' => $requestId,
    ], 202);
}

JsonResponse::send([
    'success' => false,
    'error' => ['code' => 'NOT_FOUND', 'message' => 'Resource not found.'],
    'request_id' => $requestId,
], 404);
