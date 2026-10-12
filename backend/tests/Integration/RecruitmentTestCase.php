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
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;

/**
 * Shared set-up for the release 28 tests (D-029): staff accounts, and
 * applications inserted straight into the database so each test starts from
 * the stage it needs.
 */
abstract class RecruitmentTestCase extends DatabaseTestCase
{
    use HttpRequests {
        request as protected;
        decode as protected;
    }

    protected const ORIGIN = 'https://corporate.paxofi.com';
    protected const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    protected const PASSWORD = 'Temporary pass phrase 7';

    protected MemoryMailTransport $mail;

    protected function setUp(): void
    {
        foreach (['certificates', 'talent_pool', 'application_reviewers', 'application_notes', 'job_applications', 'email_attachments', 'email_outbox', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        $this->mail = new MemoryMailTransport();
    }

    /** Inserts an application at the given stage and returns its id. */
    protected function application(string $stage = 'applied', string $email = 'ada@example.com', string $name = 'Ada Lovelace', string $type = 'fellow', ?string $createdAt = null): string
    {
        $id = Uuid::v4();
        $role = (string) self::scalar("SELECT id FROM career_opportunities WHERE engagement_type = '{$type}' AND id LIKE '7e1f0c00-%' ORDER BY sort_order, id LIMIT 1");
        $statement = self::$pdo->prepare(
            "INSERT INTO job_applications (id, reference, opportunity_id, full_name, email, hours_per_week, motivation, experience, privacy_version, stage, engagement_type, cohort, created_at)
             VALUES (:id, :reference, :role, :name, :email, 20, 'Motivation', 'Experience', '2026-10-12', :stage, :type, 'Cohort 1', :created)",
        );
        $statement->execute(['id' => $id, 'reference' => 'PIF-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)), 'role' => $role, 'name' => $name, 'email' => $email, 'stage' => $stage, 'type' => $type, 'created' => $createdAt ?? gmdate('Y-m-d H:i:s', strtotime('-30 days'))]);

        return $id;
    }

    /** A Lagos calendar day, offset from today, such as "-2 days". */
    protected static function day(string $offset): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify($offset)->format('Y-m-d');
    }

    protected function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42']);
        self::assertSame(201, $response->status(), $response->body());

        return $this->token($response);
    }

    /** Creates a staff member (as the administrator) and signs them in. */
    protected function staff(string $admin, string $email, string $name, string $role = 'human_resources'): string
    {
        $created = $this->call('POST', '/api/v1/admin/users', ['email' => $email, 'display_name' => $name, 'role' => $role, 'password' => self::PASSWORD], $admin);
        self::assertSame(201, $created->status(), $created->body());

        return $this->token($this->call('POST', '/api/v1/admin/session', ['email' => $email, 'password' => self::PASSWORD]));
    }

    protected function userId(string $email): string
    {
        return (string) self::scalar("SELECT id FROM users WHERE email = '{$email}'");
    }

    protected function token(HttpResponse $response): string
    {
        self::assertSame(1, preg_match('/^paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed>|null $payload */
    protected function call(string $method, string $uri, ?array $payload = null, ?string $cookie = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return $this->app()->handle(self::request($method, $uri, $headers, $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR), $query));
    }

    /** @return array<string, mixed> */
    protected function data(HttpResponse $response): array
    {
        return self::decode($response)['data'];
    }

    protected function app(): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN . ',https://careers.paxofi.com',
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ]);
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
            mailTransport: $this->mail,
        );
    }
}
