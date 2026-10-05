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

/** News & Insights (decision D-021), against a real MariaDB. */
final class ArticlesTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const PASSWORD = 'Temporary pass phrase 7';
    private const IMAGE = 'a1a1a1a1-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        foreach (['articles', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec("UPDATE products SET image_id = NULL, document_id = NULL");
        self::$pdo->exec("UPDATE services SET image_id = NULL, document_id = NULL");
        self::$pdo->exec('DELETE FROM catalog_revisions');
        self::$pdo->exec('DELETE FROM media_assets');
        self::$pdo->exec('DELETE FROM users');
        self::$pdo->exec("INSERT INTO media_assets (id, kind, storage_reference, filename, media_type, size_bytes, width, height, alt_text, sha256) VALUES ('" . self::IMAGE . "', 'image', '" . self::IMAGE . "', 'launch.jpg', 'image/jpeg', 1000, 1200, 630, 'The PIF 2026 launch', REPEAT('a', 64))");
    }

    public function testAnArticleIsDraftedPublishedEditedAndHidden(): void
    {
        $admin = $this->setupAdmin();
        $created = $this->call('POST', '/api/v1/admin/articles', $this->article(), $admin);
        self::assertSame(201, $created->status(), $created->body());
        $article = self::decode($created)['data'];
        self::assertSame(['draft', 'introducing-the-paxofi-innovation-fellowship', null], [$article['state'], $article['slug'], $article['live']]);
        self::assertSame(404, $this->public('/api/v1/articles/introducing-the-paxofi-innovation-fellowship')->status(), 'drafts are not public');
        self::assertSame([], self::decode($this->public('/api/v1/articles'))['data']);

        $published = self::decode($this->call('POST', "/api/v1/admin/articles/{$article['id']}/publish", null, $admin))['data'];
        self::assertSame(['published', false], [$published['state'], $published['has_draft']]);
        $public = self::decode($this->public('/api/v1/articles/introducing-the-paxofi-innovation-fellowship'))['data'];
        self::assertSame('Introducing the Paxofi Innovation Fellowship', $public['title']);
        self::assertSame(['path' => '/api/v1/media/' . self::IMAGE . '/launch.jpg', 'alt' => 'The PIF 2026 launch', 'width' => 1200, 'height' => 630], $public['image']);
        self::assertStringContainsString('## Who can apply', $public['body']);
        $list = self::decode($this->public('/api/v1/articles?category=announcement'));
        self::assertSame(1, $list['meta']['total']);
        self::assertArrayNotHasKey('body', $list['data'][0], 'the list has no bodies');
        self::assertSame(0, self::decode($this->public('/api/v1/articles?category=news'))['meta']['total']);

        $this->call('POST', "/api/v1/admin/articles/{$article['id']}/draft", ['title' => 'Introducing PIF 2026: applications open'] + $this->article(), $admin);
        self::assertSame('Introducing the Paxofi Innovation Fellowship', self::decode($this->public('/api/v1/articles/introducing-the-paxofi-innovation-fellowship'))['data']['title'], 'a draft never changes the live article');
        $this->call('POST', "/api/v1/admin/articles/{$article['id']}/publish", null, $admin);
        self::assertSame('Introducing PIF 2026: applications open', self::decode($this->public('/api/v1/articles/introducing-the-paxofi-innovation-fellowship'))['data']['title'], 'the address does not change with the title');

        self::assertSame(200, $this->call('POST', "/api/v1/admin/articles/{$article['id']}/visibility", ['visible' => false], $admin)->status());
        self::assertSame(404, $this->public('/api/v1/articles/introducing-the-paxofi-innovation-fellowship')->status());
        self::assertEqualsCanonicalizing(['article.created', 'article.published', 'article.draft_saved', 'article.published', 'article.hidden'], self::$pdo->query("SELECT action FROM audit_events WHERE action LIKE 'article.%'")->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testBusinessDevelopmentWritesAndAnAdministratorPublishes(): void
    {
        $admin = $this->setupAdmin();
        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::PASSWORD], $admin);
        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::PASSWORD]));
        $id = self::decode($this->call('POST', '/api/v1/admin/articles', $this->article(), $bd))['data']['id'];

        self::assertSame(403, $this->call('POST', "/api/v1/admin/articles/{$id}/publish", null, $bd)->status());
        self::assertSame(403, $this->call('DELETE', "/api/v1/admin/articles/{$id}", null, $bd)->status());
        self::assertSame(200, $this->call('POST', "/api/v1/admin/articles/{$id}/publish", null, $admin)->status());

        $media = self::decode($this->call('DELETE', '/api/v1/admin/media/' . self::IMAGE, null, $admin));
        self::assertStringContainsString('(article)', json_encode($media, JSON_THROW_ON_ERROR), 'a picture an article uses cannot be deleted');
        self::assertSame(200, $this->call('DELETE', "/api/v1/admin/articles/{$id}", null, $admin)->status());
        self::assertSame(0, (int) self::scalar('SELECT COUNT(*) FROM articles'));
    }

    public function testArticlesAreCheckedAndNeverCarryHtml(): void
    {
        $admin = $this->setupAdmin();
        $bad = $this->call('POST', '/api/v1/admin/articles', ['body' => 'Hello <script>alert(1)</script> and more text to pass the minimum length here.', 'category' => 'blog', 'image_id' => 'b2b2b2b2-0000-4000-8000-000000000002'] + $this->article(), $admin);
        self::assertSame(422, $bad->status());
        self::assertEqualsCanonicalizing(['body', 'category'], array_keys(self::decode($bad)['error']['details']['fields']));
        $link = $this->call('POST', '/api/v1/admin/articles', ['body' => str_repeat('Words. ', 10) . '[click](javascript:alert(1))'] + $this->article(), $admin);
        self::assertSame('Links must start with https://, http://, mailto: or / (a page on this website).', self::decode($link)['error']['details']['fields']['body']);
        $missing = $this->call('POST', '/api/v1/admin/articles', ['image_id' => 'b2b2b2b2-0000-4000-8000-000000000002'] + $this->article(), $admin);
        self::assertArrayHasKey('image_id', self::decode($missing)['error']['details']['fields']);
        self::assertSame(422, $this->public('/api/v1/articles?per_page=500')->status());
    }

    /** @return array<string, mixed> */
    private function article(): array
    {
        return [
            'title' => 'Introducing the Paxofi Innovation Fellowship',
            'category' => 'announcement',
            'summary' => 'PIF 2026 is open: remote, part-time roles with real projects and mentorship.',
            'body' => "Today we open applications for PIF 2026.\n\n## Who can apply\n\n- Students and graduates\n- Career changers\n\nRead more on [careers.paxofi.com](https://careers.paxofi.com).",
            'author_name' => 'Samuel Adeniji',
            'image_id' => self::IMAGE,
        ];
    }

    private function public(string $uri): HttpResponse
    {
        return $this->app()->handle(self::request('GET', $uri));
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
