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
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

/** Editing products and services from the staff area (decision D-011) against a real MariaDB. */
final class AdminCatalogTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const PAY = '7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    /** @var array<string, array<string, mixed>> */
    private static array $seed = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        foreach (['products', 'services'] as $table) {
            self::$seed[$table] = self::$pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        }
    }

    protected function setUp(): void
    {
        foreach (['catalog_revisions', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
        foreach (self::$seed as $table => $rows) {
            self::$pdo->exec("DELETE FROM {$table}");
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $insert = self::$pdo->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))));
                $insert->execute($row);
            }
        }
    }

    public function testDraftsStayPrivateUntilPublishedAndEveryVersionIsKept(): void
    {
        $admin = $this->setupAdmin();
        $list = self::decode($this->call('GET', '/api/v1/admin/catalog/products', cookie: $admin));
        self::assertSame(['Paxofi Pay', 'Paxofi Core Framework'], array_column($list['data'], 'name'));
        self::assertContains('shield-check', $list['meta']['icons']);

        $draft = $this->content(['summary' => 'Payments infrastructure with certainty, transparency and recovery built in.']);
        $saved = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $draft, cookie: $admin))['data'];
        self::assertSame($draft['summary'], $saved['draft']['content']['summary']);
        self::assertSame('Samuel Adeniji', $saved['draft']['author_name']);
        self::assertStringStartsWith('Digital payments infrastructure designed', $this->publicSummary('paxofi-pay'), 'a draft is not public');

        $published = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin))['data'];
        self::assertNull($published['draft']);
        self::assertSame($draft['summary'], $published['item']['content']['summary']);
        self::assertSame($draft['summary'], $this->publicSummary('paxofi-pay'), 'live on the public API');
        self::assertSame('published', $published['revisions'][0]['state']);

        $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(['summary' => 'A second wording that we will not keep at all.']), cookie: $admin);
        $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin);
        $history = self::decode($this->call('GET', '/api/v1/admin/catalog/products/' . self::PAY, cookie: $admin))['data']['revisions'];
        $first = array_values(array_filter($history, static fn (array $r): bool => $r['content']['summary'] === $draft['summary']))[0];

        $restored = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . "/revisions/{$first['id']}/restore", cookie: $admin))['data'];
        self::assertSame($draft['summary'], $restored['draft']['content']['summary'], 'an earlier version comes back as a draft');
        self::assertSame(200, $this->call('DELETE', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', cookie: $admin)->status());
        self::assertSame(409, $this->call('DELETE', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', cookie: $admin)->status());

        foreach (['catalog.draft_saved', 'catalog.published', 'catalog.restored', 'catalog.draft_discarded'] as $action) {
            self::assertGreaterThan(0, (int) self::scalar('SELECT COUNT(*) FROM audit_events WHERE action = ? AND target_type = ?', [$action, 'product']), $action);
        }
    }

    public function testNewItemsStartHiddenAndAppearWhenShown(): void
    {
        $admin = $this->setupAdmin();
        $created = $this->call('POST', '/api/v1/admin/catalog/services', [
            'name' => 'Data & AI Engineering', 'icon' => 'database', 'summary' => 'Data platforms and practical AI features built on solid foundations.', 'sort_order' => 70,
        ], cookie: $admin);
        self::assertSame(201, $created->status(), $created->body());
        $item = self::decode($created)['data']['item'];
        self::assertSame('data-ai-engineering', $item['slug']);
        self::assertFalse($item['visible']);
        self::assertNotContains('data-ai-engineering', $this->publicSlugs('services'));

        $shown = self::decode($this->call('POST', "/api/v1/admin/catalog/services/{$item['id']}/visibility", ['visible' => true], cookie: $admin))['data']['item'];
        self::assertTrue($shown['visible']);
        self::assertSame('data-ai-engineering', $this->publicSlugs('services')[6], 'shown, in display order');

        $this->call('POST', "/api/v1/admin/catalog/services/{$item['id']}/visibility", ['visible' => false], cookie: $admin);
        self::assertNotContains('data-ai-engineering', $this->publicSlugs('services'));
        $again = self::decode($this->call('POST', '/api/v1/admin/catalog/services', ['name' => 'Data & AI Engineering', 'icon' => 'database', 'summary' => 'A second item with the same name as before.'], cookie: $admin))['data']['item'];
        self::assertSame('data-ai-engineering-2', $again['slug']);
    }

    public function testBusinessDevelopmentDraftsButAnAdministratorPublishes(): void
    {
        $admin = $this->setupAdmin();
        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD], cookie: $admin);
        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));

        self::assertSame(200, $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(['summary' => 'Wording proposed by business development.']), cookie: $bd)->status());
        $denied = $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $bd);
        self::assertSame(403, $denied->status());
        self::assertSame(403, $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/visibility', ['visible' => false], cookie: $bd)->status());

        $published = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin))['data'];
        self::assertSame('Wording proposed by business development.', $published['item']['content']['summary']);
    }

    public function testInputIsValidatedAndUnknownTargetsAreRefused(): void
    {
        $admin = $this->setupAdmin();
        $bad = $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', [
            'name' => 'X', 'icon' => 'skull', 'summary' => 'short', 'points' => array_fill(0, 6, 'point'), 'sort_order' => 5000,
        ], cookie: $admin);
        self::assertSame(422, $bad->status());
        self::assertEqualsCanonicalizing(['name', 'icon', 'summary', 'points', 'sort_order'], array_keys(self::decode($bad)['error']['details']['fields']));

        self::assertSame(409, $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin)->status(), 'nothing to publish without a draft');

        $markup = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(['name' => "Paxofi\u{0000} Pay\n<b>"]), cookie: $admin))['data'];
        self::assertSame('Paxofi Pay <b>', $markup['draft']['content']['name'], 'control characters and line breaks removed; text kept as text');

        self::assertSame(404, $this->call('GET', '/api/v1/admin/catalog/careers', cookie: $admin)->status());
        self::assertSame(404, $this->call('GET', '/api/v1/admin/catalog/products/00000000-0000-4000-8000-000000000000', cookie: $admin)->status());
        self::assertSame(404, $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/revisions/00000000-0000-4000-8000-000000000000/restore', cookie: $admin)->status());
    }

    /** @return array<string, mixed> */
    private function content(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Paxofi Pay',
            'label' => 'Paxofi Product',
            'icon' => 'shield-check',
            'summary' => 'Digital payments infrastructure designed around reliability.',
            'points' => ['Transaction certainty', '', 'Recovery built in'],
            'sort_order' => 10,
        ];
    }

    private function publicSummary(string $slug): string
    {
        $data = self::decode($this->app()->handle(self::request('GET', "/api/v1/products?slug={$slug}")))['data'];

        return (string) ($data[0]['summary'] ?? '');
    }

    /** @return list<string> */
    private function publicSlugs(string $type): array
    {
        return array_column(self::decode($this->app()->handle(self::request('GET', "/api/v1/{$type}")))['data'], 'slug');
    }

    private function setupAdmin(): string
    {
        $response = $this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42']);
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
            fn (): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC')),
            new NativeStaffPasswordHasher($cheap),
        );
    }
}
