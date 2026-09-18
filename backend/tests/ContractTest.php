<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$api = file_get_contents($root . '/backend/public/index.php');
$schema = file_get_contents($root . '/database/001_initial_schema.sql');
$migration2 = file_get_contents($root . '/database/002_enquiry_metadata.sql');
$migration3 = file_get_contents($root . '/database/003_enquiry_rate_limit_index.sql');

$checks = [
    'health route exists' => str_contains($api, "'/api/v1/health'") && str_contains($api, "'status' => 'ok'"),
    'readiness route checks database' => str_contains($api, "'/api/v1/readiness'") && str_contains($api, "SELECT 1"),
    'contact route persists enquiries' => str_contains($api, 'forms/([^/]+)/submit$') && str_contains($api, 'INSERT INTO enquiries'),
    'contact validation exists' => str_contains($api, 'VALIDATION_ERROR') && str_contains($api, 'FILTER_VALIDATE_EMAIL'),
    'rate limiting exists' => str_contains($api, 'RATE_LIMITED') && str_contains($api, 'INTERVAL 10 MINUTE'),
    'enquiries table exists' => str_contains($schema, 'CREATE TABLE enquiries'),
    'metadata migration exists' => str_contains($migration2, 'source_ip') && str_contains($migration2, 'request_id'),
    'rate-limit index migration exists' => str_contains($migration3, 'idx_enquiries_source_ip_created'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed !== []) {
    fwrite(STDERR, "Contract checks failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Contract checks passed: ' . count($checks) . PHP_EOL;
