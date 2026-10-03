<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use Paxofi\Core\Logging\NullLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;

final class PublicApiTest extends DatabaseTestCase
{
    use HttpRequests;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // A draft and a future-dated product must never be exposed.
        self::$pdo->exec("INSERT INTO products (id, slug, name, summary, lifecycle_state, published_at) VALUES
            ('00000000-0000-4000-8000-000000000001', 'draft-product', 'Draft', 'x', 'draft', NULL),
            ('00000000-0000-4000-8000-000000000002', 'future-product', 'Future', 'x', 'published', CURRENT_TIMESTAMP + INTERVAL 1 DAY)");

        self::$pdo->exec("INSERT INTO career_opportunities (id, slug, title, description, lifecycle_state, published_at) VALUES
            ('00000000-0000-4000-8000-000000000010', 'backend-engineer', 'Backend Engineer', 'Build APIs.', 'published', '2026-09-20 09:00:00')");

        self::$pdo->exec("INSERT INTO content_items (id, slug, title, content_type, lifecycle_state, published_at) VALUES
            ('00000000-0000-4000-8000-000000000020', 'about-us', 'About Paxofi', 'page', 'published', '2026-09-20 09:00:00'),
            ('00000000-0000-4000-8000-000000000021', 'secret-draft', 'Draft', 'page', 'draft', NULL)");
        self::$pdo->exec("INSERT INTO content_revisions (id, content_item_id, revision_no, content_json) VALUES
            ('00000000-0000-4000-8000-000000000030', '00000000-0000-4000-8000-000000000020', 1, '{\"headline\":\"Old\"}'),
            ('00000000-0000-4000-8000-000000000031', '00000000-0000-4000-8000-000000000020', 2, '{\"headline\":\"Current\"}')");
    }

    public function testReadinessIsReadyWithDatabase(): void
    {
        $response = $this->app()->handle(self::request('GET', '/api/v1/readiness'));

        self::assertSame(200, $response->status());
        self::assertSame(['application' => true, 'database' => true], self::decode($response)['data']['checks']);
    }

    public function testProductsListsOnlyPublishedSeededItems(): void
    {
        $body = self::decode($this->app()->handle(self::request('GET', '/api/v1/products')));

        self::assertSame(['paxofi-pay', 'paxofi-core-framework'], array_column($body['data'], 'slug'), 'in display order');
        self::assertSame(['slug', 'name', 'label', 'icon', 'summary', 'points', 'sort_order', 'published_at', 'image', 'document'], array_keys($body['data'][0]));
        self::assertNull($body['data'][0]['image'], 'no picture until one is chosen in the staff area');
        self::assertNull($body['data'][0]['document']);
        self::assertSame(['Transaction certainty', 'Transparency and traceability', 'Recovery built in'], $body['data'][0]['points']);
        self::assertSame('2026-09-18T00:00:00Z', $body['data'][0]['published_at']);
        self::assertSame(2, $body['meta']['total']);
        self::assertTrue($body['meta']['published']);
    }

    public function testServicesPaginationAndSlugFilter(): void
    {
        $page2 = self::decode($this->app()->handle(self::request('GET', '/api/v1/services?page=2&per_page=4')));
        self::assertCount(2, $page2['data']);
        self::assertSame(['page' => 2, 'per_page' => 4, 'total' => 6, 'total_pages' => 2], array_diff_key($page2['meta'], ['published' => 1]));

        $one = self::decode($this->app()->handle(self::request('GET', '/api/v1/services?slug=digital-marketing-growth')));
        self::assertSame(['digital-marketing-growth'], array_column($one['data'], 'slug'));
        self::assertSame('megaphone', $one['data'][0]['icon']);
    }

    public function testCareersUseTitleAndDescription(): void
    {
        $body = self::decode($this->app()->handle(self::request('GET', '/api/v1/careers')));

        self::assertSame('Backend Engineer', $body['data'][0]['title']);
        self::assertSame('Build APIs.', $body['data'][0]['description']);
    }

    public function testContentReturnsLatestRevisionOfPublishedItemsOnly(): void
    {
        $body = self::decode($this->app()->handle(self::request('GET', '/api/v1/content?type=page')));

        self::assertSame(['about-us'], array_column($body['data'], 'slug'));
        self::assertSame(2, $body['data'][0]['revision']);
        self::assertSame(['headline' => 'Current'], $body['data'][0]['body']);
    }

    public function testContactSubmissionPersistsEnquiryAndAuditEventAtomically(): void
    {
        $response = $this->app()->handle(self::jsonPost('/api/v1/forms/contact/submit', [
            'name' => 'Grace Hopper', 'email' => 'Grace@Example.com', 'company' => 'Navy', 'message' => 'Hello Paxofi',
        ], ['user-agent' => 'IntegrationTest/1.0', 'origin' => 'https://paxofi.com']));
        $body = self::decode($response);

        self::assertSame(202, $response->status());
        self::assertSame(['accepted' => true, 'form_key' => 'contact'], $body['data']);
        self::assertSame('https://paxofi.com', $response->header('access-control-allow-origin'));

        $row = self::$pdo->query("SELECT * FROM enquiries WHERE email = 'grace@example.com'")->fetch();
        self::assertIsArray($row);
        self::assertSame('Grace Hopper', $row['name']);
        self::assertSame('203.0.113.10', $row['source_ip']);
        self::assertSame('IntegrationTest/1.0', $row['user_agent']);
        self::assertSame($body['request_id'], $row['request_id']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $row['id']);

        $audit = self::$pdo->query("SELECT * FROM audit_events WHERE action = 'enquiry.submitted' AND target_id = '{$row['id']}'")->fetch();
        self::assertIsArray($audit, 'audit event references the enquiry');
        self::assertSame('success', $audit['outcome']);
        self::assertSame($body['request_id'], $audit['request_id']);
    }

    public function testRateLimitBlocksFloodsFromOneAddress(): void
    {
        $statuses = [];
        for ($i = 0; $i < 4; $i++) {
            $statuses[] = $this->app()->handle(self::request('POST', '/api/v1/forms/contact/submit', ['content-type' => 'application/json'],
                json_encode(['name' => 'Flood', 'email' => "flood{$i}@example.com", 'message' => 'spam']), [], '198.51.100.7'))->status();
        }

        self::assertSame([202, 202, 202, 429], $statuses);
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'enquiry.rate_limited'"), 'blocked requests are logged, not stored');
        self::assertSame(3, (int) self::scalar("SELECT COUNT(*) FROM enquiries WHERE source_ip = '198.51.100.7'"));
    }

    public function testRateLimitCountsEmailAndAddressTogether(): void
    {
        // Two enquiries from 192.0.2.1 and one with the same email from elsewhere:
        // three distinct recent enquiries match (address OR email), so the limit of 3 applies.
        foreach ([['192.0.2.1', 'a1@example.com'], ['192.0.2.1', 'a2@example.com'], ['192.0.2.99', 'shared@example.com']] as [$ip, $email]) {
            self::assertSame(202, $this->submitFrom($ip, $email)->status());
        }

        self::assertSame(429, $this->submitFrom('192.0.2.1', 'shared@example.com')->status());
    }

    public function testInvalidUtf8UserAgentIsStoredScrubbed(): void
    {
        $response = $this->app()->handle(self::jsonPost('/api/v1/forms/contact/submit', [
            'name' => 'Agent', 'email' => 'agent@example.com', 'message' => 'Hello',
        ], ['user-agent' => "Legacy\xE9Browser/1.0"]));

        self::assertSame(202, $response->status());
        self::assertSame('Legacy?Browser/1.0', self::scalar("SELECT user_agent FROM enquiries WHERE email = 'agent@example.com'"));
    }

    public function testHugePageNumberIsAValidationError(): void
    {
        self::assertSame(422, $this->app()->handle(self::request('GET', '/api/v1/products?page=9223372036854775807'))->status());
    }

    public function testHoneypotSubmissionIsNotStored(): void
    {
        $response = $this->app()->handle(self::jsonPost('/api/v1/forms/contact/submit', [
            'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'buy now', 'website' => 'https://spam.example',
        ]));

        self::assertSame(202, $response->status());
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM enquiries WHERE email = 'bot@example.com'"));
    }

    public function testEnquiryIsRolledBackWhenAuditWriteFails(): void
    {
        self::$pdo->exec('RENAME TABLE audit_events TO audit_events_offline');
        try {
            $response = $this->app()->handle(self::jsonPost('/api/v1/forms/contact/submit', [
                'name' => 'Rollback', 'email' => 'rollback@example.com', 'message' => 'Should not persist',
            ]));
        } finally {
            self::$pdo->exec('RENAME TABLE audit_events_offline TO audit_events');
        }

        self::assertSame(503, $response->status());
        self::assertSame(0, (int) self::scalar("SELECT COUNT(*) FROM enquiries WHERE email = 'rollback@example.com'"));
    }

    private function submitFrom(string $ip, string $email): \Paxofi\Core\Contracts\HttpResponse
    {
        return $this->app()->handle(self::request('POST', '/api/v1/forms/contact/submit', ['content-type' => 'application/json'],
            json_encode(['name' => 'Combo', 'email' => $email, 'message' => 'hi'], JSON_THROW_ON_ERROR), [], $ip));
    }

    private function app(): ApiApplication
    {
        return new ApiApplication(Settings::fromEnvironment(self::$environment), new NullLogger(), static fn () => Connection::make(self::$environment));
    }
}
