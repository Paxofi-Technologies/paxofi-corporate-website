<?php

declare(strict_types=1);

/*
 * Front controller for the Paxofi Corporate Website API.
 * All composition lives in Bootstrap\ApiApplication; this file only bridges
 * the PHP SAPI to the PCF HTTP request/response contracts.
 */

use Paxofi\Core\Configuration\EnvLoader;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Http\Response;
use Paxofi\Core\Logging\StreamLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Http\RequestFactory;
use Paxofi\CorporateWebsite\Http\ResponseEmitter;

// Never print PHP warnings into API responses, whatever the host's php.ini
// says: they leak server paths and break status codes. Errors still reach
// the server error log.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

try {
    // backend/.env (outside the public document root) is optional; real
    // process environment variables always take precedence.
    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile) && !is_readable($envFile)) {
        // Fail closed rather than silently running with empty configuration.
        throw new RuntimeException('backend/.env exists but is not readable by PHP.');
    }
    $environment = Environment::from((new EnvLoader())->load($envFile));
    $logger = new StreamLogger(fopen('php://stderr', 'wb'));
    $application = new ApiApplication(Settings::fromEnvironment($environment), $logger);

    $response = $application->handle(RequestFactory::fromGlobals($_SERVER, $_GET, RequestFactory::readBody()));
} catch (Throwable $exception) {
    // Configuration/bootstrap failure: fail closed without leaking details.
    error_log('paxofi-corporate-website-api bootstrap failure: ' . $exception::class . ': ' . $exception->getMessage());
    $response = new Response(500, [
        'content-type' => 'application/json; charset=utf-8',
        'cache-control' => 'no-store',
        'x-content-type-options' => 'nosniff',
    ], '{"success":false,"error":{"code":"INTERNAL_ERROR","message":"The service is misconfigured."},"request_id":null}');
}

ResponseEmitter::emit($response, includeBody: $method !== 'HEAD');
