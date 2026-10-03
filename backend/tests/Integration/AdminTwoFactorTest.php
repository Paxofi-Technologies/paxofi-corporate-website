<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\Totp;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

/** Two-factor sign-in (decision D-010) against a real MariaDB. */
final class AdminTwoFactorTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const MFA_KEY = 'mfa-key-0123456789-abcdefghijklmnopqrstuvwxyz';
    private const ADMIN_EMAIL = 'founder@paxofi.com';
    private const ADMIN_PASSWORD = 'Correct horse battery 42';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    private DateTimeImmutable $now;
    private ?string $mfaKey = self::MFA_KEY;

    protected function setUp(): void
    {
        foreach (['recovery_codes', 'sessions', 'login_attempts', 'audit_events', 'user_roles', 'enquiries'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        $this->now = new DateTimeImmutable('2026-10-03 09:00:10', new DateTimeZone('UTC'));
    }

    public function testAdministratorsMustSetUpTwoFactorBeforeUsingTheStaffArea(): void
    {
        $token = $this->setupAdmin();

        $session = self::decode($this->call('GET', '/api/v1/admin/session', cookie: $token));
        self::assertTrue($session['data']['two_factor_enrollment_required']);
        self::assertFalse($session['data']['two_factor_enabled']);

        $blocked = $this->call('GET', '/api/v1/admin/enquiries', cookie: $token);
        self::assertSame(403, $blocked->status());
        self::assertSame('MFA_ENROLLMENT_REQUIRED', self::decode($blocked)['error']['code']);

        $status = self::decode($this->call('GET', '/api/v1/admin/account/two-factor', cookie: $token))['data'];
        self::assertSame(['configured' => true, 'enabled' => false, 'required' => true, 'recovery_codes_left' => 0], $status);
        self::assertSame(200, $this->call('POST', '/api/v1/admin/session/password', ['current_password' => self::ADMIN_PASSWORD, 'new_password' => 'Another good phrase 99'], cookie: $token)->status());
    }

    public function testSettingUpStoresTheSecretEncryptedAndSignsOutOtherSessions(): void
    {
        $token = $this->setupAdmin();
        $other = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));

        $setup = self::decode($this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $token))['data'];
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup['secret']);
        self::assertStringStartsWith('otpauth://totp/Paxofi:founder%40paxofi.com?secret=' . $setup['secret'], $setup['otpauth_uri']);
        $secret = self::base32Decode($setup['secret']);

        $wrong = $this->call('POST', '/api/v1/admin/account/two-factor/enable', ['code' => '000000'], cookie: $token);
        self::assertSame(422, $wrong->status());

        $enabled = self::decode($this->call('POST', '/api/v1/admin/account/two-factor/enable', ['code' => $this->codeFor($secret)], cookie: $token))['data'];
        self::assertTrue($enabled['enabled']);
        self::assertCount(10, $enabled['recovery_codes']);

        $stored = (string) self::scalar('SELECT totp_secret FROM users');
        self::assertStringStartsWith('v1.', $stored);
        self::assertStringNotContainsString($setup['secret'], $stored);
        self::assertNull(self::scalar('SELECT totp_pending_secret FROM users'));
        self::assertSame(10, (int) self::scalar('SELECT COUNT(*) FROM recovery_codes'));
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM recovery_codes WHERE code_hash = ?', [$enabled['recovery_codes'][0]]), 'only hashes are stored');

        self::assertSame(200, $this->call('GET', '/api/v1/admin/enquiries', cookie: $token)->status(), 'this session carries on');
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $other)->status(), 'other sessions are signed out');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'staff.two_factor.enabled'"));
        self::assertSame(409, $this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $token)->status());
    }

    public function testSignInAsksForTheCodeAndEachCodeWorksOnce(): void
    {
        [, $secret] = $this->enrolledAdmin();

        $first = $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);
        self::assertSame(['mfa_required' => true], self::decode($first)['data'], 'nothing about the account before the code');
        self::assertStringContainsString('Max-Age=300', (string) $first->header('set-cookie'));
        $pending = $this->token($first);

        foreach (['/api/v1/admin/session', '/api/v1/admin/enquiries'] as $path) {
            $response = $this->call('GET', $path, cookie: $pending);
            self::assertSame(401, $response->status(), $path);
            self::assertSame('MFA_REQUIRED', self::decode($response)['error']['code']);
        }

        self::assertSame(422, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $this->codeFor($secret)], cookie: $pending)->status(), 'the code used to enable cannot be reused');
        $this->now = $this->now->modify('+30 seconds');
        $verified = $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $this->codeFor($secret)], cookie: $pending);
        self::assertSame(200, $verified->status(), $verified->body());
        self::assertFalse(self::decode($verified)['data']['mfa_required']);
        self::assertStringContainsString('Max-Age=28800', (string) $verified->header('set-cookie'));
        $full = $this->token($verified);

        self::assertNotSame($pending, $full, 'a new session token after the second factor');
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $pending)->status());
        self::assertSame(200, $this->call('GET', '/api/v1/admin/enquiries', cookie: $full)->status());
        self::assertSame(409, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => '123456'], cookie: $full)->status());

        $again = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));
        self::assertSame(422, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $this->codeFor($secret)], cookie: $again)->status(), 'same code, same 30 seconds: refused');
    }

    public function testRecoveryCodesWorkOnceAndCanBeReplaced(): void
    {
        [$token, $secret, $codes] = $this->enrolledAdmin();

        $pending = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));
        self::assertSame(200, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => strtoupper($codes[0])], cookie: $pending)->status());
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'staff.two_factor.recovery_code_used'"));

        $pending = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));
        self::assertSame(422, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $codes[0]], cookie: $pending)->status(), 'a recovery code works once');
        self::assertSame(9, self::decode($this->call('GET', '/api/v1/admin/account/two-factor', cookie: $token))['data']['recovery_codes_left']);

        self::assertSame(422, $this->call('POST', '/api/v1/admin/account/two-factor/recovery-codes', ['code' => $codes[1]], cookie: $token)->status(), 'replacing needs the authenticator');
        $this->now = $this->now->modify('+30 seconds');
        $replaced = self::decode($this->call('POST', '/api/v1/admin/account/two-factor/recovery-codes', ['code' => $this->codeFor($secret)], cookie: $token))['data'];
        self::assertCount(10, $replaced['recovery_codes']);
        self::assertNotContains($codes[1], $replaced['recovery_codes']);
        $pending = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));
        self::assertSame(422, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $codes[1]], cookie: $pending)->status(), 'old codes stop working');
    }

    public function testWrongCodesAreThrottledAndThePendingSessionEnds(): void
    {
        $this->enrolledAdmin();
        $pending = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));

        for ($i = 0; $i < 5; $i++) {
            self::assertSame(422, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => sprintf('%06d', $i)], cookie: $pending)->status());
        }
        $blocked = $this->call('POST', '/api/v1/admin/session/mfa', ['code' => '999999'], cookie: $pending);

        self::assertSame(429, $blocked->status());
        self::assertSame(401, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => '999999'], cookie: $pending)->status(), 'the pending session is ended');
    }

    public function testThePasswordStepExpiresAfterFiveMinutes(): void
    {
        [, $secret] = $this->enrolledAdmin();
        $pending = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]));

        $this->now = $this->now->modify('+5 minutes');

        self::assertSame(401, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $this->codeFor($secret)], cookie: $pending)->status());
    }

    public function testOptionalForBusinessDevelopmentAndAdministratorsCanResetIt(): void
    {
        [$admin] = $this->enrolledAdmin();
        $bdId = self::decode($this->call('POST', '/api/v1/admin/users', [
            'email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD,
        ], cookie: $admin))['data']['id'];

        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));
        self::assertSame(200, $this->call('GET', '/api/v1/admin/enquiries', cookie: $bd)->status(), 'not required for Business Development');
        self::assertFalse(self::decode($this->call('GET', '/api/v1/admin/account/two-factor', cookie: $bd))['data']['required']);

        $secret = self::base32Decode(self::decode($this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $bd))['data']['secret']);
        self::assertSame(200, $this->call('POST', '/api/v1/admin/account/two-factor/enable', ['code' => $this->codeFor($secret)], cookie: $bd)->status());
        self::assertSame(422, $this->call('POST', '/api/v1/admin/account/two-factor/disable', ['password' => 'wrong'], cookie: $bd)->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/account/two-factor/disable', ['password' => self::BD_PASSWORD], cookie: $bd)->status());
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM recovery_codes WHERE user_id = ?', [$bdId]));

        $secret = self::base32Decode(self::decode($this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $bd))['data']['secret']);
        $this->now = $this->now->modify('+30 seconds');
        $this->call('POST', '/api/v1/admin/account/two-factor/enable', ['code' => $this->codeFor($secret)], cookie: $bd);

        $adminId = (string) self::scalar('SELECT id FROM users WHERE email = ?', [self::ADMIN_EMAIL]);
        self::assertSame(403, $this->call('POST', '/api/v1/admin/account/two-factor/disable', ['password' => self::ADMIN_PASSWORD], cookie: $admin)->status(), 'administrators must keep it on');
        self::assertSame(403, $this->call('POST', "/api/v1/admin/users/{$adminId}/two-factor/reset", cookie: $admin)->status(), 'not on yourself');
        self::assertSame(403, $this->call('POST', "/api/v1/admin/users/{$adminId}/two-factor/reset", cookie: $bd)->status(), 'needs users.manage');

        $reset = self::decode($this->call('POST', "/api/v1/admin/users/{$bdId}/two-factor/reset", cookie: $admin))['data'];
        self::assertFalse($reset['two_factor_enabled']);
        self::assertSame(401, $this->call('GET', '/api/v1/admin/session', cookie: $bd)->status(), 'reset signs them out');
        self::assertSame(1, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'staff.two_factor.reset' AND target_id = ?", [$bdId]));
    }

    public function testWithoutTheServerKeyTwoFactorIsUnavailableButNotEnforced(): void
    {
        $this->mfaKey = null;
        $token = $this->setupAdmin();

        self::assertSame(200, $this->call('GET', '/api/v1/admin/enquiries', cookie: $token)->status());
        self::assertSame(503, $this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $token)->status());
        self::assertFalse(self::decode($this->call('GET', '/api/v1/admin/account/two-factor', cookie: $token))['data']['configured']);
    }

    public function testAnEnabledAccountStillNeedsItsSecondFactorIfTheKeyIsRemoved(): void
    {
        [, , $codes] = $this->enrolledAdmin();
        $this->mfaKey = null;

        $pending = $this->call('POST', '/api/v1/admin/session', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);
        self::assertTrue(self::decode($pending)['data']['mfa_required'], 'removing the key never skips the second factor');
        self::assertSame(200, $this->call('POST', '/api/v1/admin/session/mfa', ['code' => $codes[2]], cookie: $this->token($pending))->status(), 'recovery codes still work');
    }

    /** @return array{string, string, list<string>} full-session token, raw secret, recovery codes */
    private function enrolledAdmin(): array
    {
        $token = $this->setupAdmin();
        $secret = self::base32Decode(self::decode($this->call('POST', '/api/v1/admin/account/two-factor/setup', cookie: $token))['data']['secret']);
        $codes = self::decode($this->call('POST', '/api/v1/admin/account/two-factor/enable', ['code' => $this->codeFor($secret)], cookie: $token))['data']['recovery_codes'];

        return [$token, $secret, $codes];
    }

    private function codeFor(string $secret): string
    {
        return Totp::code($secret, Totp::step($this->now));
    }

    private static function base32Decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $bits .= str_pad(decbin((int) strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $bytes .= chr((int) bindec($byte));
        }

        return $bytes;
    }

    private function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', [
            'setup_token' => self::SETUP_TOKEN, 'email' => self::ADMIN_EMAIL, 'display_name' => 'Samuel Adeniji', 'password' => self::ADMIN_PASSWORD,
        ]);
        self::assertSame(201, $response->status(), $response->body());

        return $this->token($response);
    }

    private function token(HttpResponse $response): string
    {
        self::assertSame(1, preg_match('/^paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed>|null $payload */
    private function call(string $method, string $uri, ?array $payload = null, ?string $cookie = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }

        return $this->app()->handle(self::request($method, $uri, $headers, $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    private function app(): ApiApplication
    {
        $values = [
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ];
        if ($this->mfaKey !== null) {
            $values['MFA_ENCRYPTION_KEY'] = $this->mfaKey;
        }
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment(Environment::from($values)),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => $this->now,
            new NativeStaffPasswordHasher($cheap),
        );
    }
}
