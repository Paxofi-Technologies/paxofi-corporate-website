<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Database\Migrator;
use Paxofi\CorporateWebsite\Infrastructure\Backup\BackupRunner;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;

/** Email alerts, password reset by email and nightly backups (decision D-016), against a real MariaDB. */
final class EmailTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const PASSWORD = 'Correct horse battery 42';

    private static string $now = '2026-10-04 10:00:00';
    private MemoryMailTransport $mail;

    protected function setUp(): void
    {
        foreach (['email_outbox', 'password_resets', 'enquiries', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        self::$now = '2026-10-04 10:00:00';
        $this->mail = new MemoryMailTransport();
    }

    public function testANewEnquiryEmailsStaffWithReplyToTheVisitor(): void
    {
        $app = $this->app();
        $response = $app->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'company' => 'Navy Labs', 'message' => "We need a payments partner.\nCan we talk?"], ['origin' => self::ORIGIN]));
        self::assertSame(202, $response->status(), $response->body());
        self::assertSame([], $this->mail->sent, 'nothing is sent before the response');

        $app->afterResponse();
        self::assertCount(1, $this->mail->sent);
        $email = $this->mail->sent[0];
        self::assertSame(['sales@paxofi.com', 'founder@paxofi.com'], $email->to);
        self::assertSame('grace@example.com', $email->replyTo);
        self::assertSame('New enquiry from Grace Hopper (Navy Labs)', $email->subject);
        self::assertStringContainsString("We need a payments partner.\nCan we talk?", $email->text);
        $id = (string) self::scalar('SELECT id FROM enquiries');
        self::assertStringContainsString('https://corporate.paxofi.com/admin/enquiries/' . $id, $email->text);
        self::assertSame('sent', self::scalar('SELECT status FROM email_outbox'));

        $spam = $this->app();
        $spam->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things right now please.', 'website' => 'http://spam.example'], ['origin' => self::ORIGIN]));
        $spam->afterResponse();
        self::assertCount(1, $this->mail->sent, 'the honeypot sends nothing');
    }

    public function testHeaderInjectionIsImpossible(): void
    {
        $app = $this->app();
        $app->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => "Eve\r\nBcc: victim@example.com", 'email' => 'eve@example.com', 'message' => 'Hello there, testing headers.'], ['origin' => self::ORIGIN]));
        $app->afterResponse();
        self::assertStringNotContainsString("\n", $this->mail->sent[0]->subject);
        self::assertSame(['sales@paxofi.com', 'founder@paxofi.com'], $this->mail->sent[0]->to);
    }

    public function testFailedEmailsAreRetriedThenGivenUpWithAnAlert(): void
    {
        $this->mail->failing = true;
        $app = $this->app();
        $app->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Please call me back soon.'], ['origin' => self::ORIGIN]));
        $app->afterResponse();
        self::assertSame(['pending', 1], [self::scalar('SELECT status FROM email_outbox'), (int) self::scalar('SELECT attempts FROM email_outbox')]);
        self::assertStringContainsString('451', (string) self::scalar('SELECT last_error FROM email_outbox'));

        // The cron job tries again once each wait has passed: 1, 5, 15, 60, 240 minutes.
        foreach (['2026-10-04 10:01:30', '2026-10-04 10:07:00', '2026-10-04 10:23:00', '2026-10-04 11:24:00', '2026-10-04 15:30:00'] as $time) {
            self::$now = $time;
            $this->app()->outboxSender()->run();
        }
        self::assertSame(['failed', 6], [self::scalar('SELECT status FROM email_outbox'), (int) self::scalar('SELECT attempts FROM email_outbox')]);

        $this->mail->failing = false;
        self::$now = '2026-10-05 10:00:00';
        $sender = $this->app();
        self::assertSame(['sent' => 0, 'failed' => 0], $sender->outboxSender()->run(), 'given up: not retried');
    }

    public function testForgottenPasswordIsResetWithAOneTimeLink(): void
    {
        $this->setupAdmin();
        $app = $this->app();
        $other = $this->call($app, 'POST', '/api/v1/admin/password-reset', ['email' => 'nobody@paxofi.com']);
        self::assertSame(202, $other->status());
        $real = $this->call($app, 'POST', '/api/v1/admin/password-reset', ['email' => 'Founder@Paxofi.com']);
        self::assertSame(202, $real->status());
        self::assertSame($this->shape($other), $this->shape($real), 'the same answer whether or not the account exists');
        $app->afterResponse();
        self::assertCount(1, $this->mail->sent);
        self::assertSame(['founder@paxofi.com'], $this->mail->sent[0]->to);
        self::assertSame(1, preg_match('~https://corporate\.paxofi\.com/admin/reset-password#([A-Za-z0-9_-]{43})~', $this->mail->sent[0]->text, $m));
        $token = $m[1];
        self::assertNotSame($token, self::scalar('SELECT token_hash FROM password_resets'), 'only a hash is stored');

        $weak = $this->call($this->app(), 'POST', '/api/v1/admin/password-reset/complete', ['token' => $token, 'password' => 'short']);
        self::assertSame(422, $weak->status());
        self::assertArrayHasKey('password', self::decode($weak)['error']['details']['fields']);

        $done = $this->app();
        self::assertSame(200, $this->call($done, 'POST', '/api/v1/admin/password-reset/complete', ['token' => $token, 'password' => 'A brand new pass phrase 7'])->status());
        $done->afterResponse();
        self::assertSame('Your Paxofi staff password was changed', $this->mail->sent[1]->subject);
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM sessions WHERE revoked_at IS NULL'), 'signed out everywhere');
        self::assertSame(422, $this->call($this->app(), 'POST', '/api/v1/admin/password-reset/complete', ['token' => $token, 'password' => 'Another new phrase 99'])->status(), 'the link works once');

        self::assertSame(200, $this->call($this->app(), 'POST', '/api/v1/admin/session', ['email' => 'founder@paxofi.com', 'password' => 'A brand new pass phrase 7'])->status());
        self::assertEqualsCanonicalizing(
            ['staff.password_reset.requested', 'staff.password_reset.completed'],
            array_values(array_unique(self::$pdo->query("SELECT action FROM audit_events WHERE action LIKE 'staff.password_reset%' AND outcome = 'success'")->fetchAll(\PDO::FETCH_COLUMN))),
        );
    }

    public function testANewStaffAccountGetsAWelcomeEmailToChooseItsOwnPassword(): void
    {
        $this->setupAdmin();
        $admin = $this->signIn();
        $app = $this->app();
        $created = $this->call($app, 'POST', '/api/v1/admin/users', ['email' => 'Opeyemi@Paxofi.com', 'display_name' => 'Opeyemi Ogunshina', 'role' => 'human_resources'], $admin);
        self::assertSame(201, $created->status(), $created->body());
        self::assertTrue(self::decode($created)['data']['welcome_email_sent']);
        $app->afterResponse();

        $welcome = $this->mail->sent[count($this->mail->sent) - 1];
        self::assertSame(['opeyemi@paxofi.com'], $welcome->to);
        self::assertSame('Your Paxofi staff account', $welcome->subject);
        self::assertStringContainsString('Samuel Adeniji has created a Paxofi staff account for you (Human Resources)', $welcome->text);
        self::assertStringContainsString('within 72 hours', $welcome->text);
        self::assertSame(1, preg_match('~https://corporate\.paxofi\.com/admin/reset-password#([A-Za-z0-9_-]{43})~', $welcome->text, $m));
        self::assertSame('2026-10-07 10:00:00', self::scalar("SELECT expires_at FROM password_resets"), '72 hours');
        self::assertSame(401, $this->call($this->app(), 'POST', '/api/v1/admin/session', ['email' => 'opeyemi@paxofi.com', 'password' => 'Guessing a pass phrase 1'])->status(), 'no usable password before the link');

        self::assertSame(200, $this->call($this->app(), 'POST', '/api/v1/admin/password-reset/complete', ['token' => $m[1], 'password' => 'Her own pass phrase 2026'])->status());
        self::assertSame(200, $this->call($this->app(), 'POST', '/api/v1/admin/session', ['email' => 'opeyemi@paxofi.com', 'password' => 'Her own pass phrase 2026'])->status());
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'staff.welcome_sent' AND outcome = 'success'"));

        // A temporary password still works the old way, without an email.
        $before = count($this->mail->sent);
        $withPassword = $this->app();
        $other = $this->call($withPassword, 'POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => 'Temporary pass phrase 7'], $admin);
        self::assertSame(201, $other->status());
        self::assertFalse(self::decode($other)['data']['welcome_email_sent']);
        $withPassword->afterResponse();
        self::assertCount($before, $this->mail->sent);
    }

    public function testWithoutEmailANewAccountNeedsATemporaryPassword(): void
    {
        $this->setupAdmin();
        $admin = $this->signIn();
        $response = $this->call($this->app(withMail: false), 'POST', '/api/v1/admin/users', ['email' => 'hr@paxofi.com', 'display_name' => 'HR', 'role' => 'human_resources'], $admin);
        self::assertSame(422, $response->status());
        self::assertStringContainsString('Email is not set up', self::decode($response)['error']['details']['fields']['password']);
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM users WHERE email = 'hr@paxofi.com'"));
    }

    public function testResetLinksExpireAndRequestsAreLimited(): void
    {
        $this->setupAdmin();
        for ($i = 0; $i < 4; $i++) {
            $app = $this->app();
            $this->call($app, 'POST', '/api/v1/admin/password-reset', ['email' => 'founder@paxofi.com']);
            $app->afterResponse();
        }
        self::assertCount(3, $this->mail->sent, 'at most 3 links an hour per account');
        preg_match('~#([A-Za-z0-9_-]{43})~', $this->mail->sent[0]->text, $m);

        self::$now = '2026-10-04 10:31:00';
        $late = $this->call($this->app(), 'POST', '/api/v1/admin/password-reset/complete', ['token' => $m[1], 'password' => 'A brand new pass phrase 7']);
        self::assertSame(422, $late->status());
        self::assertStringContainsString('expired', self::decode($late)['error']['message']);

        self::assertSame(403, $this->app()->handle(self::jsonPost('/api/v1/admin/password-reset', ['email' => 'founder@paxofi.com'], ['origin' => 'https://evil.example']))->status());
    }

    public function testWithoutEmailSettingsNothingIsQueued(): void
    {
        $app = $this->app(withMail: false);
        $app->handle(self::jsonPost('/api/v1/forms/contact/submit', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'Please call me back soon.'], ['origin' => self::ORIGIN]));
        $app->afterResponse();
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM email_outbox'));
        self::assertFalse(self::decode($this->call($this->app(withMail: false), 'POST', '/api/v1/admin/password-reset', ['email' => 'x@paxofi.com']))['data']['available']);
    }

    public function testNightlyBackupRestoresToTheSameData(): void
    {
        $this->setupAdmin();
        self::$pdo->exec("INSERT INTO enquiries (id, name, email, message, status) VALUES ('e0000000-0000-4000-8000-000000000001', 'Zoë O''Brien', 'z@example.com', 'Line one\nLine two; with a semicolon -- and quotes \"\\\\', 'new')");
        $directory = sys_get_temp_dir() . '/paxofi-backup-test-' . bin2hex(random_bytes(4));
        $media = $directory . '-media';
        mkdir($media . '/2026/10', 0700, true);
        file_put_contents($media . '/2026/10/a.txt', 'media file');
        touch($directory . '-old', 0);
        mkdir($directory, 0700);
        touch($directory . '/paxofi-database-20260901-000000.sql.gz', strtotime('2026-09-01'));

        $result = (new BackupRunner(self::$pdo, $directory, $media, 14))->run(new DateTimeImmutable('2026-10-04 02:00:00', new DateTimeZone('UTC')));
        self::assertFileExists($result['database']);
        self::assertFileExists((string) $result['media']);
        self::assertSame(1, $result['deleted'], 'copies older than 14 days are removed');
        self::assertStringContainsString('a.txt', implode(' ', array_map(static fn ($f): string => $f->getFilename(), iterator_to_array(new \RecursiveIteratorIterator(new \PharData((string) $result['media']))))));

        $restore = 'cw_restore_test';
        $server = self::server();
        self::recreate($server, $restore, 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec("USE `{$restore}`");
        foreach (Migrator::statements((string) gzdecode((string) file_get_contents($result['database']))) as $statement) {
            $server->exec($statement);
        }
        $original = self::$pdo->query('SELECT name, message FROM enquiries')->fetch(\PDO::FETCH_ASSOC);
        self::assertSame($original, $server->query('SELECT name, message FROM enquiries')->fetch(\PDO::FETCH_ASSOC));
        self::assertSame(self::$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), $server->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame(self::$pdo->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn(), $server->query('SELECT MAX(version) FROM schema_migrations')->fetchColumn());
        $server->exec("DROP DATABASE `{$restore}`");
    }

    /** @return array<string, mixed> */
    private function shape(HttpResponse $response): array
    {
        $data = self::decode($response);
        unset($data['request_id']);

        return $data;
    }

    private function setupAdmin(): void
    {
        $response = $this->call($this->app(), 'POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => self::PASSWORD]);
        self::assertSame(201, $response->status(), $response->body());
    }

    /** Signs the founder in; returns the session cookie value. */
    private function signIn(): string
    {
        $response = $this->call($this->app(), 'POST', '/api/v1/admin/session', ['email' => 'founder@paxofi.com', 'password' => self::PASSWORD]);
        self::assertSame(1, preg_match('/^(?:__Host-)?paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed> $payload */
    private function call(ApiApplication $app, string $method, string $uri, array $payload, ?string $cookie = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }

        return $app->handle(self::request($method, $uri, $headers, json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    private function app(bool $withMail = true): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
            'ENQUIRY_ALERT_TO' => 'sales@paxofi.com, founder@paxofi.com, not-an-address',
            'ERROR_ALERT_TO' => 'it@paxofi.com',
        ]);
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => new DateTimeImmutable(self::$now, new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
            mailTransport: $withMail ? $this->mail : null,
        );
    }
}
