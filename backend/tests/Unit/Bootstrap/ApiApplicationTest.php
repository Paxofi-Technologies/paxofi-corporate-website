<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Bootstrap;

use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * End-to-end through the real middleware pipeline and PCF router, with the
 * database deliberately unreachable.
 */
final class ApiApplicationTest extends TestCase
{
    use HttpRequests;

    private int $connectAttempts = 0;

    public function testHealthNeverTouchesTheDatabase(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/health'));
        $body = self::decode($response);

        self::assertSame(200, $response->status());
        self::assertSame('ok', $body['data']['status']);
        self::assertSame($response->header('x-request-id'), $body['request_id']);
        self::assertSame(0, $this->connectAttempts);
    }

    public function testEveryResponseCarriesSecurityHeaders(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/nope'));

        self::assertSame('nosniff', $response->header('x-content-type-options'));
        self::assertSame('DENY', $response->header('x-frame-options'));
        self::assertSame('no-store', $response->header('cache-control'));
        self::assertNotNull($response->header('content-security-policy'));
    }

    public function testNavigationIsServedWithoutDatabase(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/navigation'));

        self::assertSame(200, $response->status());
        self::assertCount(5, self::decode($response)['data']);
        self::assertSame(0, $this->connectAttempts);
    }

    public function testReadinessReportsDatabaseOutageWithoutLeakingDetails(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/readiness'));
        $body = self::decode($response);

        self::assertSame(503, $response->status());
        self::assertSame(['application' => true, 'database' => false], $body['error']['details']['checks']);
        self::assertStringNotContainsString('db.internal', $response->body());
    }

    public function testCatalogReturns503WhenDatabaseIsDown(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/products'));

        self::assertSame(503, $response->status());
        self::assertSame('SERVICE_UNAVAILABLE', self::decode($response)['error']['code']);
    }

    public function testInvalidEnquiryIsRejectedBeforeConnecting(): void
    {
        $response = $this->app()->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => 'A', 'email' => 'bad', 'message' => 'Hi']));

        self::assertSame(422, $response->status());
        self::assertArrayHasKey('email', self::decode($response)['error']['details']['fields']);
        self::assertSame(0, $this->connectAttempts);
    }

    public function testUnknownFormIs404(): void
    {
        $response = $this->app()->handle(self::jsonPost('/api/v1/forms/newsletter/submit', []));

        self::assertSame(404, $response->status());
    }

    public function testWrongMethodIs405(): void
    {
        $response = $this->app()->handle(self::request('DELETE', '/api/v1/products'));

        self::assertSame(405, $response->status());
        self::assertSame('METHOD_NOT_ALLOWED', self::decode($response)['error']['code']);
    }

    public function testInvalidQueryIs422(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/services?per_page=1000'));

        self::assertSame(422, $response->status());
    }

    public function testCorsPreflightForConfiguredFrontendOrigin(): void
    {
        $response = $this->app()->handle(self::request('OPTIONS', '/api/v1/forms/contact/submit', [
            'origin' => 'https://paxofi.com',
            'access-control-request-method' => 'POST',
        ]));

        self::assertSame(204, $response->status());
        self::assertSame('https://paxofi.com', $response->header('access-control-allow-origin'));
        self::assertNotNull($response->header('x-request-id'));
    }

    public function testProductionNeverEnablesDebugAndSendsHsts(): void
    {
        $settings = Settings::fromEnvironment(Environment::from(['APP_ENV' => 'production', 'APP_DEBUG' => 'true']));
        self::assertFalse($settings->debug);

        $response = (new ApiApplication($settings, new NullLogger(), fn () => throw new RuntimeException('x')))->handle(self::request('GET', '/api/v1/health'));
        self::assertNotNull($response->header('strict-transport-security'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function adminRoutes(): iterable
    {
        foreach (require dirname(__DIR__, 3) . '/config/routes.php' as [$method, $path, $auth]) {
            if ($auth === 'admin') {
                yield $method . ' ' . $path => [$method, $path];
            }
        }
    }

    #[DataProvider('adminRoutes')]
    public function testAdminRoutesAreNotExposedUntilAuthExists(string $method, string $path): void
    {
        self::assertSame(404, $this->app()->handle(self::request($method, $path))->status());
    }

    public function testRegisteredPublicRoutesMatchEndpointInventory(): void
    {
        $inventory = [];
        foreach (require dirname(__DIR__, 3) . '/config/routes.php' as [$method, $path, $auth]) {
            if ($auth !== 'admin') {
                $inventory[] = [$method, $path];
            }
        }

        self::assertEqualsCanonicalizing($inventory, ApiApplication::PUBLIC_ROUTES);
    }

    private function app(): ApiApplication
    {
        $settings = Settings::fromEnvironment(Environment::from([
            'APP_ENV' => 'testing',
            'CORS_ALLOWED_ORIGINS' => 'https://paxofi.com, https://www.paxofi.com',
        ]));

        return new ApiApplication($settings, new NullLogger(), function (): never {
            $this->connectAttempts++;
            throw new RuntimeException('connect to db.internal refused');
        });
    }
}
