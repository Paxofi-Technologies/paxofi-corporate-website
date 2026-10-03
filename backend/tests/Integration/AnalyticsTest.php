<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Http\Request;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

/** Cookieless visitor analytics (decision D-014) against a real MariaDB. */
final class AnalyticsTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    private static string $now = '2026-10-03 10:00:00';

    protected function setUp(): void
    {
        foreach (['analytics_daily', 'analytics_sources', 'analytics_devices', 'analytics_visitors', 'analytics_salts', 'enquiries', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        self::$now = '2026-10-03 10:00:00';
    }

    public function testPageViewsAreCountedWithoutPersonalData(): void
    {
        $this->view(['path' => '/products', 'entry' => true, 'referrer' => 'https://www.google.com/search?q=paxofi', 'width' => 390], '198.51.100.7');
        $this->view(['path' => '/products/', 'entry' => false, 'width' => 390], '198.51.100.7');
        $this->view(['path' => '/', 'entry' => true, 'referrer' => '', 'width' => 1440], '198.51.100.8');
        $this->view(['path' => '/contact', 'entry' => true, 'referrer' => 'https://corporate.paxofi.com/about', 'width' => 800], '198.51.100.8');
        $this->view(['path' => '/no-such-page?x=1', 'width' => 1440], '198.51.100.9');

        self::assertSame(['views' => 5, 'visitors' => 3], $this->totals(''), 'three different visitors today');
        self::assertSame(['views' => 2, 'visitors' => 1], $this->totals('/products'), 'same visitor twice: two views, one visitor');
        self::assertSame(['views' => 1, 'visitors' => 1], $this->totals('(other)'), 'unknown pages are grouped, with no query string');
        self::assertSame(
            ['(direct)' => 1, 'google.com' => 1],
            self::$pdo->query('SELECT source, views FROM analytics_sources ORDER BY source')->fetchAll(\PDO::FETCH_KEY_PAIR),
            'only the first page of a visit counts its source; this site itself is not a source',
        );
        self::assertSame(
            ['desktop' => 2, 'phone' => 2, 'tablet' => 1],
            self::$pdo->query('SELECT device, views FROM analytics_devices ORDER BY device')->fetchAll(\PDO::FETCH_KEY_PAIR),
        );

        $stored = implode(' ', self::$pdo->query('SELECT visitor FROM analytics_visitors')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertStringNotContainsString('198.51.100', $stored, 'no IP address is stored');
        self::assertMatchesRegularExpression('/^([0-9a-f]{64} ?)+$/', $stored);
        self::assertSame('0', (string) self::scalar("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'analytics%' AND COLUMN_NAME IN ('ip', 'source_ip', 'user_agent')"));
    }

    public function testOptOutsBotsAndStaffPagesAreNotCounted(): void
    {
        $this->view(['path' => '/'], '198.51.100.7', ['dnt' => '1']);
        $this->view(['path' => '/'], '198.51.100.7', ['sec-gpc' => '1']);
        $this->view(['path' => '/'], '198.51.100.7', ['user-agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)']);
        $this->view(['path' => '/'], '198.51.100.7', ['user-agent' => '']);
        $this->view(['path' => '/admin/enquiries'], '198.51.100.7');
        $bad = $this->app()->handle(self::request('POST', '/api/v1/analytics/pageview', ['content-type' => 'text/plain', 'user-agent' => self::BROWSER], '{not json'));

        self::assertSame(204, $bad->status(), 'always 204, so a page never reacts to analytics');
        self::assertSame('0', (string) self::scalar('SELECT COUNT(*) FROM analytics_daily'));
        self::assertNull($bad->header('set-cookie'));
    }

    public function testYesterdaysVisitorHashesAreDeletedWhenANewDayStarts(): void
    {
        $this->view(['path' => '/'], '198.51.100.7');
        $firstSalt = self::scalar('SELECT salt FROM analytics_salts');

        self::$now = '2026-10-04 00:05:00';
        $this->view(['path' => '/'], '198.51.100.7');

        self::assertSame(['2026-10-04'], self::$pdo->query('SELECT DISTINCT day FROM analytics_visitors')->fetchAll(\PDO::FETCH_COLUMN), 'only today is kept');
        self::assertSame(['2026-10-04'], self::$pdo->query('SELECT day FROM analytics_salts')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertNotSame($firstSalt, self::scalar('SELECT salt FROM analytics_salts'), 'a new random salt each day: visitors cannot be linked across days');
        self::assertSame('2', (string) self::scalar("SELECT COUNT(*) FROM analytics_daily WHERE path = ''"), 'daily totals stay');
    }

    public function testStaffSeeTheReportWithEnquiries(): void
    {
        $this->view(['path' => '/', 'entry' => true, 'referrer' => 'https://www.linkedin.com/feed', 'width' => 1280], '198.51.100.7');
        $this->view(['path' => '/contact', 'width' => 1280], '198.51.100.7');
        self::$pdo->exec("INSERT INTO enquiries (id, name, email, message, status, created_at) VALUES
            ('e0000000-0000-4000-8000-000000000001', 'A', 'a@example.com', 'Hello there, a real enquiry.', 'new', '2026-10-03 09:00:00'),
            ('e0000000-0000-4000-8000-000000000002', 'B', 'b@example.com', 'Buy cheap things now please.', 'spam', '2026-10-03 09:30:00')");

        self::assertSame(401, $this->call('GET', '/api/v1/admin/analytics')->status());

        $admin = $this->setupAdmin();
        $response = $this->call('GET', '/api/v1/admin/analytics?days=7', $admin);
        self::assertSame(200, $response->status(), $response->body());
        $report = self::decode($response)['data'];
        self::assertSame(7, $report['days']);
        self::assertCount(7, $report['daily']);
        self::assertSame(['2026-09-27', '2026-10-03'], [$report['from'], $report['to']]);
        self::assertSame(['day' => '2026-10-03', 'views' => 2, 'visitors' => 1, 'enquiries' => 1], $report['daily'][6], 'spam is not counted as an enquiry');
        self::assertSame(['views' => 2, 'visitors' => 1, 'enquiries' => 1], $report['totals']);
        self::assertSame(['/', '/contact'], array_column($report['pages'], 'path'));
        self::assertSame([['source' => 'linkedin.com', 'views' => 1]], $report['sources']);
        self::assertSame([['device' => 'desktop', 'views' => 2]], $report['devices']);

        self::assertSame(30, self::decode($this->call('GET', '/api/v1/admin/analytics?days=1000', $admin))['data']['days'], 'only 7, 30 or 90 days');
    }

    /** @param array<string, string> $headers */
    private function view(array $payload, string $ip, array $headers = []): void
    {
        $request = new Request('POST', '/api/v1/analytics/pageview', $headers + [
            'content-type' => 'text/plain;charset=UTF-8',
            'origin' => self::ORIGIN,
            'user-agent' => self::BROWSER,
        ], json_encode($payload, JSON_THROW_ON_ERROR), [], ['client_ip' => $ip]);
        $response = $this->app()->handle($request);
        self::assertSame(204, $response->status(), $response->body());
        self::assertSame(self::ORIGIN, $response->header('access-control-allow-origin'));
    }

    /** @return array{views: int, visitors: int} */
    private function totals(string $path): array
    {
        $row = self::$pdo->query('SELECT views, visitors FROM analytics_daily WHERE path = ' . self::$pdo->quote($path))->fetch(\PDO::FETCH_ASSOC);

        return ['views' => (int) $row['views'], 'visitors' => (int) $row['visitors']];
    }

    private function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', null, ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42']);
        self::assertSame(1, preg_match('/^paxofi_admin=([^;]+);/', (string) $response->header('set-cookie'), $match), $response->body());

        return $match[1];
    }

    /** @param array<string, mixed>|null $payload */
    private function call(string $method, string $uri, ?string $cookie = null, ?array $payload = null): HttpResponse
    {
        $headers = ['origin' => self::ORIGIN, 'content-type' => 'application/json'];
        if ($cookie !== null) {
            $headers['cookie'] = 'paxofi_admin=' . $cookie;
        }

        return $this->app()->handle(self::request($method, $uri, $headers, $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR)));
    }

    private function app(): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ]);
        $cheap = defined('PASSWORD_ARGON2ID') ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : ['cost' => 4];

        return new ApiApplication(
            Settings::fromEnvironment($environment),
            new NullLogger(),
            fn () => Connection::make(self::$environment),
            fn (): DateTimeImmutable => new DateTimeImmutable(self::$now, new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
        );
    }
}
