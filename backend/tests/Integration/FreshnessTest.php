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
use Paxofi\CorporateWebsite\Tests\Support\MemoryMailTransport;

/** Content review dates, the weekly reminder and redirects (decision D-025), against a real MariaDB. */
final class FreshnessTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    private MemoryMailTransport $mail;

    protected function setUp(): void
    {
        foreach (['content_reviews', 'redirects', 'email_outbox', 'sessions', 'login_attempts', 'audit_events', 'user_roles', 'catalog_revisions'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('UPDATE media_assets SET uploaded_by = NULL');
        self::$pdo->exec('UPDATE articles SET created_by = NULL, draft_author_id = NULL');
        self::$pdo->exec('DELETE FROM page_revisions');
        self::$pdo->exec('DELETE FROM users');
        $this->mail = new MemoryMailTransport();
    }

    public function testLiveContentIsListedAndCanBeMarkedReviewed(): void
    {
        $admin = $this->setupAdmin();
        $list = self::decode($this->call('GET', '/api/v1/admin/reviews', cookie: $admin));
        $items = $list['data'];
        $byType = array_count_values(array_column($items, 'type'));
        self::assertSame(9, $byType['page'], 'every page of text, including the menu and footer');
        self::assertSame(10, $byType['industry']);
        self::assertGreaterThanOrEqual(3, $byType['product']);
        self::assertSame(count($items), $list['meta']['counts']['none'], 'nothing has a date yet');
        $today = $list['meta']['today'];

        $pay = $this->item($items, 'product', 'Paxofi Pay');
        self::assertSame('/products#paxofi-pay', $pay['path']);
        self::assertSame('/industries/education', $this->item($items, 'industry', 'Education')['path']);
        self::assertNull($this->item($items, 'page', 'Menu and footer')['path']);

        $reviewed = $this->call('POST', '/api/v1/admin/reviews/product/' . $pay['key'], ['action' => 'reviewed', 'months' => 6, 'note' => '  Status checked with the  product team. '], cookie: $admin);
        self::assertSame(200, $reviewed->status(), $reviewed->body());
        $row = self::decode($reviewed)['data'];
        $expected = (new DateTimeImmutable($today))->modify('+6 months')->format('Y-m-d');
        self::assertSame([$expected, 'ok', 'Samuel Adeniji', 'Status checked with the product team.'], [$row['review_by'], $row['status'], $row['last_reviewed_by'], $row['note']]);
        self::assertNotNull($row['last_reviewed_at']);

        $soon = (new DateTimeImmutable($today))->modify('+10 days')->format('Y-m-d');
        $dated = self::decode($this->call('POST', '/api/v1/admin/reviews/page/about', ['review_by' => $soon], cookie: $admin))['data'];
        self::assertSame([$soon, 'due', null], [$dated['review_by'], $dated['status'], $dated['last_reviewed_at']]);

        // A date set later keeps who last reviewed it.
        $moved = self::decode($this->call('POST', '/api/v1/admin/reviews/product/' . $pay['key'], ['review_by' => $soon], cookie: $admin))['data'];
        self::assertSame(['Samuel Adeniji', 'Status checked with the product team.'], [$moved['last_reviewed_by'], $moved['note']]);

        self::$pdo->exec("UPDATE content_reviews SET review_by = DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE item_type = 'page' AND item_key = 'about'");
        $after = self::decode($this->call('GET', '/api/v1/admin/reviews', cookie: $admin));
        self::assertSame(['page', 'about', 'overdue'], [$after['data'][0]['type'], $after['data'][0]['key'], $after['data'][0]['status']], 'overdue first');
        self::assertSame(1, $after['meta']['counts']['overdue']);

        foreach (['content_review.reviewed', 'content_review.date_set'] as $action) {
            self::assertGreaterThan(0, (int) self::scalar('SELECT COUNT(*) FROM audit_events WHERE action = ?', [$action]), $action);
        }
    }

    public function testReviewInputIsChecked(): void
    {
        $admin = $this->setupAdmin();
        $past = (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('-2 days')->format('Y-m-d');
        $cases = [
            [['action' => 'reviewed', 'months' => 5], 'months'],
            [['review_by' => $past], 'review_by'],
            [['review_by' => '2027-02-30'], 'review_by'],
            [['review_by' => '2099-01-01'], 'review_by'],
            [['action' => 'reviewed', 'note' => str_repeat('x', 301)], 'note'],
        ];
        foreach ($cases as [$payload, $field]) {
            $response = $this->call('POST', '/api/v1/admin/reviews/page/home', $payload, cookie: $admin);
            self::assertSame(422, $response->status(), json_encode($payload));
            self::assertArrayHasKey($field, self::decode($response)['error']['details']['fields']);
        }
        self::assertSame(422, $this->call('POST', '/api/v1/admin/reviews/page/home', ['nothing' => true], cookie: $admin)->status());
        self::assertSame(404, $this->call('POST', '/api/v1/admin/reviews/page/no-such-page', ['action' => 'reviewed'], cookie: $admin)->status());
        self::assertSame(404, $this->call('POST', '/api/v1/admin/reviews/recipe/1', ['action' => 'reviewed'], cookie: $admin)->status());
        self::assertSame(200, $this->call('POST', '/api/v1/admin/reviews/page/home', ['review_by' => null], cookie: $admin)->status(), 'a date can be cleared');

        $bd = $this->businessDevelopment($admin);
        self::assertSame(200, $this->call('POST', '/api/v1/admin/reviews/page/home', ['action' => 'reviewed', 'months' => 12], cookie: $bd)->status(), 'editors review content');
        self::assertSame(401, $this->call('GET', '/api/v1/admin/reviews')->status());
    }

    public function testAdministratorsGetAWeeklyReminderWhileSomethingIsDue(): void
    {
        $admin = $this->setupAdmin();
        $app = $this->app(withMail: true);
        self::assertSame(0, $app->contentReviewReminder()->run(), 'nothing due, nothing sent');

        $this->call('POST', '/api/v1/admin/reviews/page/about', ['action' => 'reviewed', 'months' => 3], cookie: $admin);
        self::$pdo->exec("UPDATE content_reviews SET review_by = DATE_SUB(CURDATE(), INTERVAL 5 DAY) WHERE item_key = 'about'");
        $this->call('POST', '/api/v1/admin/reviews/page/contact', ['review_by' => (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+5 days')->format('Y-m-d')], cookie: $admin);
        $this->call('POST', '/api/v1/admin/reviews/page/careers', ['review_by' => (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->modify('+60 days')->format('Y-m-d')], cookie: $admin);

        self::assertSame(0, $this->app(withMail: false)->contentReviewReminder()->run(), 'no email set up, no reminder');
        self::assertSame(2, $app->contentReviewReminder()->run(), 'overdue and due within two weeks');
        $app->outboxSender()->run(10, 10.0);
        self::assertCount(1, $this->mail->sent);
        $email = $this->mail->sent[0];
        self::assertSame(['founder@paxofi.com'], $email->to, 'active administrators');
        self::assertSame('2 website items due for review (1 overdue)', $email->subject);
        self::assertStringContainsString('Page text: About (overdue since', $email->text);
        self::assertStringContainsString('Page text: Contact (review by', $email->text);
        self::assertStringNotContainsString('Careers', $email->text);
        self::assertStringContainsString('https://corporate.paxofi.com/admin/content?tab=reviews', $email->text);

        self::assertSame(0, $app->contentReviewReminder()->run(), 'at most once a week');
        self::$pdo->exec("UPDATE audit_events SET created_at = DATE_SUB(created_at, INTERVAL 8 DAY) WHERE action = 'content_review.reminder_sent'");
        self::assertSame(2, $app->contentReviewReminder()->run(), 'again a week later');
    }

    public function testRedirectsSendRetiredAddressesOnAndAreChecked(): void
    {
        $admin = $this->setupAdmin();
        $created = $this->call('POST', '/api/v1/admin/redirects', ['from' => 'https://corporate.paxofi.com/Insights/Old-Article/', 'to' => '/insights/new-article', 'note' => 'Renamed'], cookie: $admin);
        self::assertSame(201, $created->status(), $created->body());
        $redirect = self::decode($created)['data'];
        self::assertSame(['/insights/old-article', '/insights/new-article', 'Renamed', 'Samuel Adeniji'], [$redirect['from'], $redirect['to'], $redirect['note'], $redirect['created_by']]);
        self::assertSame(201, $this->call('POST', '/api/v1/admin/redirects', ['from' => '/brochure', 'to' => 'https://paxofi.com/brochure.pdf'], cookie: $admin)->status());

        $public = self::decode($this->app()->handle(self::request('GET', '/api/v1/redirects')))['data'];
        self::assertEqualsCanonicalizing([['from' => '/insights/old-article', 'to' => '/insights/new-article'], ['from' => '/brochure', 'to' => 'https://paxofi.com/brochure.pdf']], $public);

        $refused = [
            [['from' => '/about', 'to' => '/'], 'from'],
            [['from' => '/admin/users', 'to' => '/'], 'from'],
            [['from' => '/industries/education', 'to' => '/industries'], 'from'],
            [['from' => 'about-us', 'to' => '/about'], 'from'],
            [['from' => '/a/../b', 'to' => '/about'], 'from'],
            [['from' => '/old', 'to' => 'javascript:alert(1)'], 'to'],
            [['from' => '/old', 'to' => 'http://example.com'], 'to'],
            [['from' => '/old', 'to' => '//evil.example'], 'to'],
            [['from' => '/old', 'to' => '/old/'], 'to'],
            [['from' => '/older', 'to' => '/insights/old-article'], 'to'],
            [['from' => '/insights/new-article', 'to' => '/about'], 'from'],
        ];
        foreach ($refused as [$payload, $field]) {
            $response = $this->call('POST', '/api/v1/admin/redirects', $payload, cookie: $admin);
            self::assertSame(422, $response->status(), json_encode($payload) . ' ' . $response->body());
            self::assertArrayHasKey($field, self::decode($response)['error']['details']['fields'], json_encode($payload));
        }
        self::assertSame(409, $this->call('POST', '/api/v1/admin/redirects', ['from' => '/insights/old-article/', 'to' => '/insights'], cookie: $admin)->status(), 'one redirect per address');

        $changed = $this->call('PATCH', '/api/v1/admin/redirects/' . $redirect['id'], ['from' => '/insights/old-article', 'to' => '/insights?category=news#latest'], cookie: $admin);
        self::assertSame(200, $changed->status(), $changed->body());
        self::assertSame('/insights?category=news#latest', self::decode($changed)['data']['to']);

        $bd = $this->businessDevelopment($admin);
        self::assertSame(200, $this->call('GET', '/api/v1/admin/redirects', cookie: $bd)->status(), 'editors can see redirects');
        self::assertSame(403, $this->call('POST', '/api/v1/admin/redirects', ['from' => '/x', 'to' => '/'], cookie: $bd)->status());
        self::assertSame(403, $this->call('DELETE', '/api/v1/admin/redirects/' . $redirect['id'], cookie: $bd)->status());

        self::assertSame(200, $this->call('DELETE', '/api/v1/admin/redirects/' . $redirect['id'], cookie: $admin)->status());
        self::assertSame(404, $this->call('DELETE', '/api/v1/admin/redirects/' . $redirect['id'], cookie: $admin)->status());
        self::assertCount(1, self::decode($this->call('GET', '/api/v1/admin/redirects', cookie: $admin))['data']);
        foreach (['redirect.created', 'redirect.updated', 'redirect.deleted'] as $action) {
            self::assertGreaterThan(0, (int) self::scalar('SELECT COUNT(*) FROM audit_events WHERE action = ?', [$action]), $action);
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function item(array $items, string $type, string $title): array
    {
        foreach ($items as $item) {
            if ($item['type'] === $type && $item['title'] === $title) {
                return $item;
            }
        }
        self::fail("{$type} {$title} not listed");
    }

    private function businessDevelopment(string $admin): string
    {
        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD], cookie: $admin);

        return $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));
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

    private function app(bool $withMail = false): ApiApplication
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
            mailTransport: $withMail ? $this->mail : null,
        );
    }
}
