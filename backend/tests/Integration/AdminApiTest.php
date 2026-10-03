<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

/** Staff sign-in, roles and the admin API (decision D-009) against a real MariaDB. */
final class AdminApiTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const ADMIN_EMAIL = 'founder@paxofi.com';
    private const ADMIN_PASSWORD = 'Correct horse battery 42';

    private DateTimeImmutable $now;
    private string $appEnv = 'testing';

    protected function setUp(): void
    {
        foreach (['sessions', 'login_attempts', 'audit_events', 'user_roles', 'enquiries'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        $this->now = new DateTimeImmutable('2026-10-03 09:00:00', new DateTimeZone('UTC'));
    }

    public function testSetupCreatesTheFirstAdministratorOnceAndSignsIn(): void
    {
        self::assertTrue(self::decode($this->call('GET', '/api/v1/admin/setup'))['data']['available']);
        self::assertSame(403, $this->call('POST', '/api/v1/admin/setup', ['setup_token' => 'wrong'] + $this->adminFields())->status());

        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN] + $this->adminFields());

        self::assertSame(201, $response->status());
        $user = self::decode($response)['data'];
        self::assertSame('administrator', $user['role']);
        self::assertContains('users.manage', $user['permissions']);
        $cookie = (string) $response->header('set-cookie');
        self::assertMatchesRegularExpression('/^paxofi_admin=[A-Za-z0-9_-]{43}; Path=\/; Max-Age=28800; HttpOnly; SameSite=Strict$/', $cookie);
        self::assertSame(self::ADMIN_EMAIL, self::decode($this->call('GET', '/api/v1/admin/session', cookie: $this->token($response)))['data']['email']);

        self::assertFalse(self::decode($this->call('GET', '/api/v1/admin/setup'))['data']['available']);
        self::assertSame(409, $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN] + $this->adminFields())->status());
        $hash = (string) self::scalar('SELECT password_hash FROM users');
        self::assertStringNotContainsString(self::ADMIN_PASSWORD, $hash);
        self::assertTrue(password_verify(self::ADMIN_PASSWORD, $hash));
    }

    public function testSetupRejectsAWeakPassword(): void
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'password' => 'short'] + $this->adminFields());

        self::assertSame(422, $response->status());
        self::assertArrayHasKey('password', self::decode($response)['error']['details']['fields']);
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM users'));
    }

    public function testProductionCookieIsHostOnlyAndSecure(): void
    {
        $this->appEnv = 'production';
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN] + $this->adminFields());

        self::assertStringStartsWith('__Host-paxofi_admin=', (string) $response->header('set-cookie'));
        self::assertStringEndsWith('; Secure', (string) $response->header('set-cookie'));
        self::assertStringNotContainsString('Domain=', (string) $response->header('set-cookie'));
    }

    public function testWrongEmailAndWrongPasswordLookTheSame(): void
    {
        $this->setupAdmin();

        $wrongPassword = $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => 'not the password at all']);
        $unknownEmail = $this->call('POST', '/api/v1/admin/session', ['email' => 'nobody@paxofi.com', 'password' => 'not the password at all']);

        self::assertSame(401, $wrongPassword->status());
        self::assertSame(401, $unknownEmail->status());
        self::assertSame(self::decode($wrongPassword)['error']['message'], self::decode($unknownEmail)['error']['message']);
        self::assertNull($wrongPassword->header('set-cookie'));
    }

    public function testRepeatedFailuresAreThrottled(): void
    {
        $this->setupAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => 'wrong password ' . $i]);
        }

        $response = $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);

        self::assertSame(429, $response->status());
        self::assertSame('900', $response->header('retry-after'));
    }

    public function testInboxListsFiltersShowsAndUpdatesEnquiriesWithAudit(): void
    {
        $token = $this->setupAdmin();
        self::$pdo->exec("INSERT INTO enquiries (id, name, email, company, message, status, created_at) VALUES
            ('00000000-0000-4000-8000-0000000000e1', 'Ada Lovelace', 'ada@example.com', 'Engines', 'Payments integration please', 'new', '2026-10-02 10:00:00'),
            ('00000000-0000-4000-8000-0000000000e2', 'Grace Hopper', 'grace@example.com', NULL, 'Compiler question', 'replied', '2026-10-01 10:00:00')");

        $list = self::decode($this->call('GET', '/api/v1/admin/enquiries', cookie: $token));
        self::assertSame(['Ada Lovelace', 'Grace Hopper'], array_column($list['data'], 'name'));
        self::assertSame(1, $list['meta']['counts']['new']);
        self::assertSame(['Grace Hopper'], array_column(self::decode($this->call('GET', '/api/v1/admin/enquiries?status=replied', cookie: $token))['data'], 'name'));
        self::assertSame(['Ada Lovelace'], array_column(self::decode($this->call('GET', '/api/v1/admin/enquiries?q=engin', cookie: $token))['data'], 'name'));

        $detail = self::decode($this->call('GET', '/api/v1/admin/enquiries/00000000-0000-4000-8000-0000000000e1', cookie: $token))['data'];
        self::assertSame('Payments integration please', $detail['message']);

        $updated = $this->call('PATCH', '/api/v1/admin/enquiries/00000000-0000-4000-8000-0000000000e1', ['status' => 'in_progress'], cookie: $token);
        self::assertSame('in_progress', self::decode($updated)['data']['status']);
        self::assertSame(422, $this->call('PATCH', '/api/v1/admin/enquiries/00000000-0000-4000-8000-0000000000e1', ['status' => 'deleted'], cookie: $token)->status());
        self::assertSame(404, $this->call('GET', '/api/v1/admin/enquiries/00000000-0000-4000-8000-0000000000ff', cookie: $token)->status());

        $actor = (string) self::scalar('SELECT id FROM users');
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'enquiry.status.in_progress' AND actor_id = ?", [$actor]));
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'enquiry.viewed' AND target_id = '00000000-0000-4000-8000-0000000000e1'"));
        $audit = self::decode($this->call('GET', '/api/v1/admin/audit?action=enquiry', cookie: $token));
        self::assertSame('Samuel Adeniji', $audit['data'][0]['actor_name']);
    }

    public function testStateChangesNeedTheWebsiteOrigin(): void
    {
        $token = $this->setupAdmin();
        $request = self::request('PATCH', '/api/v1/admin/enquiries/00000000-0000-4000-8000-0000000000e1', [
            'content-type' => 'application/json',
            'cookie' => 'paxofi_admin=' . $token,
            'origin' => 'https://evil.example',
        ], '{"status":"spam"}');

        self::assertSame(403, $this->app()->handle($request)->status());
    }

    public function testBusinessDevelopmentCanWorkTheInboxButNotManageUsers(): void
    {
        $admin = $this->setupAdmin();
        $created = $this->call('POST', '/api/v1/admin/users', [
            'email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => 'Temporary pass phrase 7',
        ], cookie: $admin);
        self::assertSame(201, $created->status());
        self::assertSame(409, $this->call('POST', '/api/v1/admin/users', [
            'email' => 'BD@paxofi.com', 'display_name' => 'Again', 'role' => 'business_development', 'password' => 'Temporary pass phrase 7',
        ], cookie: $admin)->status());

        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => 'Temporary pass phrase 7']));
        self::assertSame(200, $this->call('GET', '/api/v1/admin/enquiries', cookie: $bd)->status());
        self::assertSame(403, $this->call('GET', '/api/v1/admin/users', cookie: $bd)->status());
        self::assertSame(403, $this->call('GET', '/api/v1/admin/audit', cookie: $bd)->status());

        $bdId = self::decode($created)['data']['id'];
        self::assertSame(200, $this->call('PATCH', "/api/v1/admin/users/{$bdId}", ['status' => 'disabled'], cookie: $admin)->status());
        self::assertSame(401, $this->call('GET', '/api/v1/admin/enquiries', cookie: $bd)->status(), 'disabling ends their sessions');
        self::assertSame(401, $this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => 'Temporary pass phrase 7'])->status());
    }

    public function testAdministratorsCannotLockThemselvesOut(): void
    {
        $token = $this->setupAdmin();
        $id = (string) self::scalar('SELECT id FROM users');

        $disable = $this->call('PATCH', "/api/v1/admin/users/{$id}", ['status' => 'disabled'], cookie: $token);
        $demote = $this->call('PATCH', "/api/v1/admin/users/{$id}", ['role' => 'business_development'], cookie: $token);

        self::assertSame(422, $disable->status());
        self::assertSame(422, $demote->status());
        self::assertSame('active', self::scalar('SELECT status FROM users'));
    }

    public function testSessionsExpireAfterInactivityAndOnSignOut(): void
    {
        $token = $this->setupAdmin();
        $this->now = $this->now->modify('+29 minutes');
        self::assertSame(200, $this->call('GET', '/api/v1/admin/session', cookie: $token)->status(), 'activity keeps it alive');
        $this->now = $this->now->modify('+29 minutes');
        self::assertSame(200, $this->call('GET', '/api/v1/admin/session', cookie: $token)->status());
        $this->now = $this->now->modify('+31 minutes');
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $token)->status(), 'idle for 31 minutes');

        $token = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));
        $signOut = $this->call('DELETE', '/api/v1/admin/session', cookie: $token);
        self::assertStringContainsString('Max-Age=0', (string) $signOut->header('set-cookie'));
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $token)->status());
    }

    public function testSessionsEndAfterEightHoursEvenWhenActive(): void
    {
        $token = $this->setupAdmin();
        for ($minutes = 0; $minutes < 480; $minutes += 20) {
            $this->now = $this->now->modify('+20 minutes');
            $this->call('GET', '/api/v1/admin/session', cookie: $token);
        }

        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $token)->status());
    }

    public function testChangingPasswordSignsOutOtherSessions(): void
    {
        $first = $this->setupAdmin();
        $second = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));

        self::assertSame(422, $this->call('POST', '/api/v1/admin/session/password', ['current_password' => 'wrong', 'new_password' => 'Another good phrase 99'], cookie: $first)->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/session/password', ['current_password' => self::ADMIN_PASSWORD, 'new_password' => 'Another good phrase 99'], cookie: $first)->status());

        self::assertSame(200, $this->call('GET', '/api/v1/admin/session', cookie: $first)->status());
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $second)->status());
        self::assertSame(401, $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD])->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => 'Another good phrase 99'])->status());
    }

    public function testOnlyTheTokenHashIsStored(): void
    {
        $token = $this->setupAdmin();

        self::assertSame(hash('sha256', $token), self::scalar('SELECT id FROM sessions'));
    }

    /** @return array<string, string> */
    private function adminFields(): array
    {
        return ['email' => self::ADMIN_EMAIL, 'display_name' => 'Samuel Adeniji', 'password' => self::ADMIN_PASSWORD];
    }

    private function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN] + $this->adminFields());
        self::assertSame(201, $response->status(), $response->body());

        return $this->token($response);
    }

    private function token(HttpResponse $response): string
    {
        self::assertSame(1, preg_match('/^(?:__Host-)?paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed>|null $payload */
    private function call(string $method, string $uri, ?array $payload = null, ?string $cookie = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN];
        if ($cookie !== null) {
            $headers['cookie'] = ($this->appEnv === 'production' ? '__Host-paxofi_admin=' : 'paxofi_admin=') . $cookie;
        }
        $body = '';
        if ($payload !== null) {
            $headers['content-type'] = 'application/json';
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        return $this->app()->handle(self::request($method, $uri, $headers, $body));
    }

    private function app(): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => $this->appEnv,
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ]);
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            getenv('ADMIN_TEST_DEBUG') ? new \Paxofi\Core\Logging\StreamLogger(fopen('php://stderr', 'wb')) : new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => $this->now,
            new NativeStaffPasswordHasher($cheap),
        );
    }
}
