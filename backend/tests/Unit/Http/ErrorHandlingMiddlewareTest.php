<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Http\Response;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Http\Middleware\ErrorHandlingMiddleware;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlingMiddlewareTest extends TestCase
{
    use HttpRequests;

    /** @var list<array{string, array<string, mixed>}> */
    private array $logged = [];

    public function testValidationFailureBecomes422WithFieldDetails(): void
    {
        $response = $this->middleware()->process(self::request('POST', '/'), self::handlerReturning(new ValidationFailed(['email' => 'Enter a valid email address.'])));
        $body = self::decode($response);

        self::assertSame(422, $response->status());
        self::assertFalse($body['success']);
        self::assertSame('VALIDATION_ERROR', $body['error']['code']);
        self::assertSame(['email' => 'Enter a valid email address.'], $body['error']['details']['fields']);
    }

    public function testRateLimitSetsRetryAfter(): void
    {
        $response = $this->middleware()->process(self::request('POST', '/'), self::handlerReturning(new RateLimited(600)));

        self::assertSame(429, $response->status());
        self::assertSame('600', $response->header('retry-after'));
    }

    public function testDependencyFailureIs503AndLogged(): void
    {
        $response = $this->middleware()->process(self::request('GET', '/'), self::handlerReturning(new DependencyUnavailable(previous: new RuntimeException('SQLSTATE[HY000] secret-host'))));

        self::assertSame(503, $response->status());
        self::assertStringNotContainsString('secret-host', $response->body());
        self::assertSame('dependency.unavailable', $this->logged[0][0]);
        self::assertStringContainsString('secret-host', (string) $this->logged[0][1]['cause']);
    }

    public function testUnexpectedExceptionIsGeneric500(): void
    {
        $response = $this->middleware()->process(self::request('GET', '/'), self::handlerReturning(new RuntimeException('stack details')));
        $body = self::decode($response);

        self::assertSame(500, $response->status());
        self::assertSame('INTERNAL_ERROR', $body['error']['code']);
        self::assertStringNotContainsString('stack details', $response->body());
        self::assertSame('http.unhandled_exception', $this->logged[0][0]);
    }

    public function testRouterPlainText404And405BecomeJson(): void
    {
        $notFound = $this->middleware()->process(self::request('GET', '/x'), self::handlerReturning(new Response(404, [], 'Not Found')));
        $notAllowed = $this->middleware()->process(self::request('PUT', '/x'), self::handlerReturning(new Response(405, ['allow' => 'GET'], 'Method Not Allowed')));

        self::assertSame('NOT_FOUND', self::decode($notFound)['error']['code']);
        self::assertSame('METHOD_NOT_ALLOWED', self::decode($notAllowed)['error']['code']);
        self::assertSame('GET', $notAllowed->header('allow'));
    }

    private function middleware(): ErrorHandlingMiddleware
    {
        $logged = &$this->logged;

        return new ErrorHandlingMiddleware(new class ($logged) implements Logger {
            /** @param list<array{string, array<string, mixed>}> $logged */
            public function __construct(private array &$logged)
            {
            }

            public function debug(string $message, array $context = []): void
            {
            }

            public function info(string $message, array $context = []): void
            {
            }

            public function warning(string $message, array $context = []): void
            {
                $this->logged[] = [$message, $context];
            }

            public function error(string $message, array $context = []): void
            {
                $this->logged[] = [$message, $context];
            }
        });
    }
}
