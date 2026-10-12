<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;

/** The staff Email page (decision D-031), against a real MariaDB. */
final class MailDiagnosticsTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';

    protected function setUp(): void
    {
        foreach (['mail_settings', 'email_outbox', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
    }

    protected function tearDown(): void
    {
        // Saved settings would switch email on for the other test classes.
        self::$pdo->exec('DELETE FROM mail_settings');
    }

    public function testSettingsSavedHereWinOverEnvAndThePasswordIsEncrypted(): void
    {
        $env = ['MAIL_TRANSPORT' => 'smtp', 'MAIL_HOST' => 'mail.example.com', 'MAIL_FROM_ADDRESS' => 'no-reply@paxofi.com', 'MAIL_PASSWORD' => 'from-env'];
        $admin = $this->setupAdmin($env);

        $initial = self::decode($this->call('GET', '/api/v1/admin/mail', null, $admin, env: $env))['data'];
        self::assertTrue($initial['configured']);
        self::assertSame(['mail.example.com', 'server'], [$initial['fields']['host']['value'], $initial['fields']['host']['source']]);
        self::assertSame([null, true, 'server'], [$initial['fields']['password']['value'], $initial['fields']['password']['set'], $initial['fields']['password']['source']], 'the password is never shown');
        self::assertStringNotContainsString('from-env', json_encode($initial, JSON_THROW_ON_ERROR));

        $saved = $this->call('PATCH', '/api/v1/admin/mail', ['host' => 'mail.paxofi.com', 'port' => '587', 'encryption' => 'tls', 'username' => 'no-reply@paxofi.com'], $admin, env: $env);
        self::assertSame(200, $saved->status(), $saved->body());
        $data = self::decode($saved)['data'];
        self::assertSame(['mail.paxofi.com', 'staff', '587', 'tls'], [$data['fields']['host']['value'], $data['fields']['host']['source'], $data['fields']['port']['value'], $data['fields']['encryption']['value']]);
        self::assertSame(['smtp', 'server'], [$data['fields']['transport']['value'], $data['fields']['transport']['source']], 'what is not saved still comes from .env');
        self::assertFalse($data['can_store_password']);

        // Without MFA_ENCRYPTION_KEY the password cannot be stored safely, so it is refused (encryption: MailDiagnosticsUnitTest).
        $refused = $this->call('PATCH', '/api/v1/admin/mail', ['password' => 'Mailbox pass 99'], $admin, env: $env);
        self::assertSame(422, $refused->status());
        self::assertStringContainsString('MFA_ENCRYPTION_KEY', self::decode($refused)['error']['details']['fields']['password']);
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM mail_settings WHERE setting_key = 'password'"));

        // An empty value goes back to .env.
        $back = self::decode($this->call('PATCH', '/api/v1/admin/mail', ['host' => ''], $admin, env: $env))['data'];
        self::assertSame(['mail.example.com', 'server'], [$back['fields']['host']['value'], $back['fields']['host']['source']]);

        $bad = $this->call('PATCH', '/api/v1/admin/mail', ['host' => 'not a host', 'port' => '99999', 'from_address' => 'nope', 'transport' => 'carrier-pigeon'], $admin, env: $env);
        self::assertSame(422, $bad->status());
        self::assertEqualsCanonicalizing(['host', 'port', 'from_address', 'transport'], array_keys(self::decode($bad)['error']['details']['fields']));
        self::assertSame(2, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'mail.settings_updated'"));
    }

    public function testEmailCanBeSwitchedOnFromTheStaffAreaAloneAndTheCronSeesIt(): void
    {
        $admin = $this->setupAdmin([]);
        $off = self::decode($this->call('GET', '/api/v1/admin/mail', null, $admin))['data'];
        self::assertFalse($off['configured']);
        self::assertStringContainsString('no transport', $off['problem']);
        self::assertFalse($this->app([])->mailConfigured());

        $on = self::decode($this->call('PATCH', '/api/v1/admin/mail', ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => '1', 'encryption' => 'none', 'from_address' => 'no-reply@paxofi.com'], $admin))['data'];
        self::assertTrue($on['configured']);
        self::assertTrue($this->app([])->mailConfigured(), 'bin/send-mail.php checks this, so the cron job uses the saved settings');

        // Nothing listens on port 1: the test shows the real error instead of failing silently.
        $test = self::decode($this->call('POST', '/api/v1/admin/mail/test', null, $admin))['data'];
        self::assertFalse($test['ok']);
        self::assertStringContainsString('Could not connect to the mail server 127.0.0.1:1', (string) $test['error']);
        self::assertSame('founder@paxofi.com', $test['to']);
    }

    public function testATestEmailAndTheQueueAreSentAndFailuresAreShown(): void
    {
        $memory = new MemoryMailTransport();
        $admin = $this->setupAdmin([], $memory);

        $test = self::decode($this->call('POST', '/api/v1/admin/mail/test', null, $admin, $memory))['data'];
        self::assertSame([true, null], [$test['ok'], $test['error']]);
        self::assertSame(['founder@paxofi.com'], $memory->sent[0]->to);

        self::$pdo->exec("INSERT INTO email_outbox (id, kind, recipients, subject, body_text, next_attempt_at, created_at) VALUES ('00000000-0000-4000-8000-0000000000a1', 'candidate_acknowledgement', 'ada@example.com', 'Hello', 'Body', UTC_TIMESTAMP() - INTERVAL 1 DAY, UTC_TIMESTAMP() - INTERVAL 1 DAY)");
        $status = self::decode($this->call('GET', '/api/v1/admin/mail', null, $admin, $memory))['data'];
        self::assertSame(1, $status['queue']['pending']);
        self::assertNotNull($status['queue']['oldest_pending_at']);

        $run = self::decode($this->call('POST', '/api/v1/admin/mail/send-queued', null, $admin, $memory))['data'];
        self::assertSame([true, 1, 0], [$run['ok'], $run['sent'], $run['failed']]);
        self::assertSame([0, 1], [$run['status']['queue']['pending'], $run['status']['queue']['sent_last_day']]);

        $broken = new class () implements MailTransport {
            public function send(Email $email): void
            {
                throw new \RuntimeException('connection reset by the hosting firewall');
            }
        };
        $failed = self::decode($this->call('POST', '/api/v1/admin/mail/test', null, $admin, $broken))['data'];
        self::assertSame([false, 'RuntimeException: connection reset by the hosting firewall'], [$failed['ok'], $failed['error']]);
    }

    public function testOnlyAdministratorsUseTheEmailPage(): void
    {
        $admin = $this->setupAdmin([]);
        $this->call('POST', '/api/v1/admin/users', ['email' => 'hr@paxofi.com', 'display_name' => 'HR', 'role' => 'human_resources', 'password' => 'Temporary pass phrase 7'], $admin);
        $hr = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'hr@paxofi.com', 'password' => 'Temporary pass phrase 7']));
        foreach ([['GET', '/api/v1/admin/mail'], ['PATCH', '/api/v1/admin/mail'], ['POST', '/api/v1/admin/mail/test'], ['POST', '/api/v1/admin/mail/send-queued']] as [$method, $uri]) {
            self::assertSame(403, $this->call($method, $uri, [], $hr)->status(), "{$method} {$uri}");
        }
        self::assertSame(401, $this->call('GET', '/api/v1/admin/mail')->status());
    }

    /** @param array<string, string> $env */
    private function setupAdmin(array $env, ?MailTransport $transport = null): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42'], null, $transport, $env);
        self::assertSame(201, $response->status(), $response->body());

        return $this->token($response);
    }

    private function token(HttpResponse $response): string
    {
        self::assertSame(1, preg_match('/^paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string> $env
     */
    private function call(string $method, string $uri, ?array $payload = null, ?string $cookie = null, ?MailTransport $transport = null, array $env = []): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }

        return $this->app($env, $transport)->handle(self::request($method, $uri, $headers, $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    /** @param array<string, string> $env */
    private function app(array $env, ?MailTransport $transport = null): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ] + $env);
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
            mailTransport: $transport,
        );
    }
}
