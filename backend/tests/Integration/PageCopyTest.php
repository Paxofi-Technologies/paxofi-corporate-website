<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopySchema;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Security\NativeStaffPasswordHasher;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

/** Page text edited in the staff area (decision D-015), against a real MariaDB. */
final class PageCopyTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    protected function setUp(): void
    {
        foreach (['page_revisions', 'sessions', 'login_attempts', 'audit_events', 'user_roles'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('DELETE FROM users');
    }

    public function testDraftsStayPrivateUntilPublishedAndVersionsCanBeRestored(): void
    {
        self::assertSame([], self::decode($this->call('GET', '/api/v1/pages/home'))['data']['fields'], 'never published: the website uses its built-in wording');

        $admin = $this->setupAdmin();
        $list = self::decode($this->call('GET', '/api/v1/admin/pages', cookie: $admin))['data'];
        self::assertSame(['home', 'about', 'services', 'products', 'careers', 'contact', 'site'], array_column($list, 'page'));
        self::assertNull($list[0]['published_at']);

        $page = self::decode($this->call('GET', '/api/v1/admin/pages/home', cookie: $admin))['data'];
        self::assertSame('Home', $page['label']);
        self::assertNull($page['live']);
        $fields = array_column($page['fields'], 'default', 'key');
        self::assertSame('Technology for a', $fields['hero_title_start']);

        $edited = ['hero_title_start' => 'Technology for an even', 'cta_title' => "Got a hard\nproblem?"] + $fields;
        $draft = self::decode($this->call('POST', '/api/v1/admin/pages/home/draft', ['fields' => $edited], cookie: $admin))['data'];
        self::assertSame('Got a hard problem?', $draft['draft']['fields']['cta_title'], 'line breaks removed: plain text only');
        self::assertSame([], self::decode($this->call('GET', '/api/v1/pages/home'))['data']['fields'], 'a draft is not public');

        $published = self::decode($this->call('POST', '/api/v1/admin/pages/home/publish', cookie: $admin))['data'];
        self::assertNull($published['draft']);
        self::assertCount(1, $published['revisions']);
        $public = self::decode($this->call('GET', '/api/v1/pages/home'));
        self::assertSame('Technology for an even', $public['data']['fields']['hero_title_start']);
        self::assertSame(count($fields), count($public['data']['fields']));

        $this->call('POST', '/api/v1/admin/pages/home/draft', ['fields' => ['cta_title' => 'Third wording entirely.'] + $fields], cookie: $admin);
        $this->call('POST', '/api/v1/admin/pages/home/publish', cookie: $admin);
        $history = self::decode($this->call('GET', '/api/v1/admin/pages/home', cookie: $admin))['data']['revisions'];
        self::assertCount(2, $history);

        $restored = self::decode($this->call('POST', "/api/v1/admin/pages/home/revisions/{$history[1]['id']}/restore", cookie: $admin))['data'];
        self::assertSame('Got a hard problem?', $restored['draft']['fields']['cta_title'], 'the older version comes back as a draft');
        self::assertSame('Third wording entirely.', self::decode($this->call('GET', '/api/v1/pages/home'))['data']['fields']['cta_title'], 'live until published');

        self::assertSame(200, $this->call('DELETE', '/api/v1/admin/pages/home/draft', cookie: $admin)->status());
        self::assertSame(409, $this->call('DELETE', '/api/v1/admin/pages/home/draft', cookie: $admin)->status());
        self::assertEqualsCanonicalizing(
            ['page.draft_saved', 'page.published', 'page.restored', 'page.draft_discarded'],
            array_values(array_unique(self::$pdo->query("SELECT action FROM audit_events WHERE target_type = 'page' AND target_id = 'home'")->fetchAll(\PDO::FETCH_COLUMN))),
        );
    }

    public function testFieldsAreValidatedAndOnlyAnAdministratorPublishes(): void
    {
        $admin = $this->setupAdmin();
        $fields = array_column(self::decode($this->call('GET', '/api/v1/admin/pages/contact', cookie: $admin))['data']['fields'], 'default', 'key');

        $bad = $this->call('POST', '/api/v1/admin/pages/contact/draft', ['fields' => ['hero_title' => str_repeat('x', 71), 'signoff' => '   '] + $fields], cookie: $admin);
        self::assertSame(422, $bad->status());
        self::assertSame(['hero_title' => 'Use at most 70 characters.', 'signoff' => 'Enter some text.'], self::decode($bad)['error']['details']['fields']);
        self::assertSame(404, $this->call('GET', '/api/v1/admin/pages/privacy', cookie: $admin)->status(), 'legal pages stay in code');
        self::assertSame(404, $this->call('GET', '/api/v1/pages/nope')->status());

        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD], cookie: $admin);
        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));
        $saved = $this->call('POST', '/api/v1/admin/pages/contact/draft', ['fields' => ['where_text' => 'Lagos, Abuja and online.'] + $fields], cookie: $bd);
        self::assertSame(200, $saved->status(), $saved->body());
        self::assertSame('Business Dev', self::decode($saved)['data']['draft']['author_name']);
        self::assertSame(403, $this->call('POST', '/api/v1/admin/pages/contact/publish', cookie: $bd)->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/pages/contact/publish', cookie: $admin)->status());
        self::assertSame('Lagos, Abuja and online.', self::decode($this->call('GET', '/api/v1/pages/contact'))['data']['fields']['where_text']);
        self::assertSame(401, $this->call('GET', '/api/v1/admin/pages')->status());
    }

    public function testMenuAndFooterLinksAreCheckedAndCanBeHidden(): void
    {
        $admin = $this->setupAdmin();
        $fields = array_column(self::decode($this->call('GET', '/api/v1/admin/pages/site', cookie: $admin))['data']['fields'], 'default', 'key');
        self::assertSame(['/about', '/contact', 'https://careers.paxofi.com'], [$fields['menu_1_link'], $fields['menu_button_link'], $fields['footer_careers_link']]);

        $bad = $this->call('POST', '/api/v1/admin/pages/site/draft', ['fields' => [
            'menu_1_link' => 'javascript:alert(1)',
            'menu_2_link' => '//evil.example',
            'menu_3_link' => 'http://plain.example',
            'menu_6_label' => 'Blog',
            'footer_email' => 'not an email',
            'menu_button_label' => '',
        ] + $fields], cookie: $admin);
        self::assertSame(422, $bad->status());
        $errors = self::decode($bad)['error']['details']['fields'];
        self::assertEqualsCanonicalizing(['menu_1_link', 'menu_2_link', 'menu_3_link', 'menu_button_label', 'menu_6_link', 'footer_email'], array_keys($errors));
        self::assertStringContainsString('https://', $errors['menu_1_link']);
        self::assertSame('Fill in both the name and the link, or leave both empty.', $errors['menu_6_link']);
        self::assertSame('Enter some text.', $errors['menu_button_label'], 'the button is always shown');

        // Hide Careers, add a Blog link to an outside site, change the footer email.
        $edited = ['menu_4_label' => '', 'menu_4_link' => '', 'menu_5_label' => 'Blog', 'menu_5_link' => 'https://blog.paxofi.com/', 'footer_email' => 'hello@paxofi.com'] + $fields;
        self::assertSame(200, $this->call('POST', '/api/v1/admin/pages/site/draft', ['fields' => $edited], cookie: $admin)->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/pages/site/publish', cookie: $admin)->status());
        $live = self::decode($this->call('GET', '/api/v1/pages/site'))['data']['fields'];
        self::assertSame(['', '', 'Blog', 'https://blog.paxofi.com/'], [$live['menu_4_label'], $live['menu_4_link'], $live['menu_5_label'], $live['menu_5_link']], 'an emptied optional link is published as empty, which hides it');
    }

    public function testBuiltInWordingFitsEveryField(): void
    {
        $schema = PageCopySchema::default();
        foreach ($schema->pageKeys() as $page) {
            $defaults = array_column($schema->page($page)['fields'], 'default', 'key');
            self::assertSame($defaults, $schema->validate($page, $defaults), "{$page}: built-in wording is valid as it is");
            self::assertCount(count($defaults), array_unique(array_keys($defaults)), "{$page}: unique keys");
        }
    }

    private function setupAdmin(): string
    {
        return $this->token($this->call('POST', '/api/v1/admin/setup', ['setup_token' => self::SETUP_TOKEN, 'email' => 'founder@paxofi.com', 'display_name' => 'Samuel Adeniji', 'password' => 'Correct horse battery 42']));
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
