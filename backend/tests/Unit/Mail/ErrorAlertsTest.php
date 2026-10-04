<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Mail;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Infrastructure\Mail\FileAlertThrottle;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Error alerts by email (D-016): sent after the response, at most once an hour per problem. */
final class ErrorAlertsTest extends TestCase
{
    use HttpRequests;

    private string $state;
    private string $now = '2026-10-04 10:00:00';
    private MemoryMailTransport $mail;

    protected function setUp(): void
    {
        $this->state = sys_get_temp_dir() . '/paxofi-alert-test-' . bin2hex(random_bytes(4));
        $this->mail = new MemoryMailTransport();
    }

    public function testADatabaseOutageIsEmailedOnceAnHour(): void
    {
        $app = $this->app();
        self::assertSame(503, $app->handle(self::request('GET', '/api/v1/products'))->status());
        self::assertSame([], $this->mail->sent, 'not during the request');
        $app->afterResponse();
        self::assertCount(1, $this->mail->sent);
        self::assertSame(['it@paxofi.com'], $this->mail->sent[0]->to);
        self::assertSame('[Paxofi Technologies API] The API cannot reach the database', $this->mail->sent[0]->subject);
        self::assertStringContainsString('Path: /api/v1/products', $this->mail->sent[0]->text);
        self::assertStringNotContainsString('db-password', $this->mail->sent[0]->text);

        $this->now = '2026-10-04 10:30:00';
        $again = $this->app();
        $again->handle(self::request('GET', '/api/v1/services'));
        $again->afterResponse();
        self::assertCount(1, $this->mail->sent, 'the same problem is not repeated within the hour');

        $this->now = '2026-10-04 11:05:00';
        $later = $this->app();
        $later->handle(self::request('GET', '/api/v1/products'));
        $later->afterResponse();
        self::assertCount(2, $this->mail->sent);
    }

    public function testNoAlertsWithoutRecipients(): void
    {
        $app = $this->app(recipients: '');
        $app->handle(self::request('GET', '/api/v1/products'));
        $app->afterResponse();
        self::assertSame([], $this->mail->sent);
    }

    private function app(string $recipients = 'it@paxofi.com'): ApiApplication
    {
        return new ApiApplication(
            Settings::fromEnvironment(Environment::from(['APP_ENV' => 'testing', 'ERROR_ALERT_TO' => $recipients, 'DB_PASSWORD' => 'db-password'])),
            new NullLogger(),
            static fn () => throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'),
            fn (): DateTimeImmutable => new DateTimeImmutable($this->now, new DateTimeZone('UTC')),
            mailTransport: $this->mail,
            alertThrottle: new FileAlertThrottle($this->state),
        );
    }
}
