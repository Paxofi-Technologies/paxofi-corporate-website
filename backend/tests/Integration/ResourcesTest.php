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

/** The Resources page (decision D-023): administrators list documents from the media library, against a real MariaDB. */
final class ResourcesTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const BROCHURE = 'e0e0e0e0-0000-4000-8000-000000000001';
    private const GUIDE = 'e0e0e0e0-0000-4000-8000-000000000002';
    private const PICTURE = 'e0e0e0e0-0000-4000-8000-000000000003';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    protected function setUp(): void
    {
        foreach (['sessions', 'login_attempts', 'audit_events', 'user_roles', 'catalog_revisions'] as $table) {
            self::$pdo->exec("DELETE FROM {$table}");
        }
        self::$pdo->exec('UPDATE products SET image_id = NULL, document_id = NULL');
        self::$pdo->exec('UPDATE services SET image_id = NULL, document_id = NULL');
        self::$pdo->exec('UPDATE industries SET image_id = NULL, document_id = NULL');
        self::$pdo->exec('UPDATE articles SET image_id = NULL');
        self::$pdo->exec('DELETE FROM media_assets');
        self::$pdo->exec('DELETE FROM users');
        $insert = self::$pdo->prepare(
            "INSERT INTO media_assets (id, kind, filename, media_type, storage_reference, lifecycle_state, size_bytes, width, height, alt_text, title)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)",
        );
        $insert->execute([self::BROCHURE, 'document', 'paxofi-pay-brochure.pdf', 'application/pdf', self::BROCHURE, 1258291, null, null, null, 'Paxofi Pay brochure']);
        $insert->execute([self::GUIDE, 'document', 'pif-2026-guide.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', self::GUIDE, 40960, null, null, null, 'PIF 2026 applicant guide']);
        $insert->execute([self::PICTURE, 'image', 'office.png', 'image/png', self::PICTURE, 2048, 1200, 630, 'Our office', null]);
    }

    public function testAnAdministratorListsDocumentsAndTheyAppearOnThePublicList(): void
    {
        $admin = $this->setupAdmin();
        self::assertSame([], $this->publicList()['data'], 'nothing is listed until an administrator lists it');

        $listed = $this->call('POST', '/api/v1/admin/media/' . self::BROCHURE . '/resource', ['listed' => true, 'category' => 'brochure', 'summary' => "  How Paxofi Pay keeps\npayments certain and traceable.  "], cookie: $admin);
        self::assertSame(200, $listed->status(), $listed->body());
        self::assertSame(['listed' => true, 'category' => 'brochure', 'summary' => 'How Paxofi Pay keeps payments certain and traceable.'], array_intersect_key(self::decode($listed)['data']['resource'], array_flip(['listed', 'category', 'summary'])));
        $this->call('POST', '/api/v1/admin/media/' . self::GUIDE . '/resource', ['listed' => true, 'category' => 'guide', 'summary' => 'Everything applicants need to know about PIF 2026.'], cookie: $admin);

        $public = $this->publicList();
        self::assertEqualsCanonicalizing(['Paxofi Pay brochure', 'PIF 2026 applicant guide'], array_column($public['data'], 'title'));
        $brochure = array_values(array_filter($public['data'], static fn (array $r): bool => $r['category'] === 'brochure'))[0];
        self::assertSame(['title', 'summary', 'category', 'category_label', 'format', 'size_bytes', 'path', 'listed_at'], array_keys($brochure));
        self::assertSame(['Brochures', 'PDF', 1258291, '/api/v1/media/' . self::BROCHURE . '/paxofi-pay-brochure.pdf'], [$brochure['category_label'], $brochure['format'], $brochure['size_bytes'], $brochure['path']]);
        self::assertSame('Guides', $public['meta']['categories']['guide']);

        $blocked = $this->call('DELETE', '/api/v1/admin/media/' . self::BROCHURE, cookie: $admin);
        self::assertSame(409, $blocked->status(), 'a listed document cannot be deleted');
        self::assertStringContainsString('the Resources page', self::decode($blocked)['error']['message']);

        $off = self::decode($this->call('POST', '/api/v1/admin/media/' . self::BROCHURE . '/resource', ['listed' => false, 'category' => 'brochure', 'summary' => 'How Paxofi Pay keeps payments certain and traceable.'], cookie: $admin))['data'];
        self::assertFalse($off['resource']['listed']);
        self::assertSame('brochure', $off['resource']['category'], 'kept for listing again later');
        self::assertSame(['PIF 2026 applicant guide'], array_column($this->publicList()['data'], 'title'));
        $files = array_column(self::decode($this->call('GET', '/api/v1/admin/media', cookie: $admin))['data'], 'used_by', 'id');
        self::assertSame([], $files[self::BROCHURE], 'once off the page it may be deleted again');

        foreach (['media.resource_listed', 'media.resource_unlisted'] as $action) {
            self::assertGreaterThan(0, (int) self::scalar('SELECT COUNT(*) FROM audit_events WHERE action = ?', [$action]), $action);
        }
    }

    public function testListingIsCheckedAndOnlyAdministratorsMayList(): void
    {
        $admin = $this->setupAdmin();
        $missing = $this->call('POST', '/api/v1/admin/media/' . self::BROCHURE . '/resource', ['listed' => true, 'category' => '', 'summary' => 'short'], cookie: $admin);
        self::assertSame(422, $missing->status());
        self::assertEqualsCanonicalizing(['category', 'summary'], array_keys(self::decode($missing)['error']['details']['fields']));
        self::assertSame(422, $this->call('POST', '/api/v1/admin/media/' . self::BROCHURE . '/resource', ['listed' => true, 'category' => 'secret', 'summary' => 'A long enough description.'], cookie: $admin)->status());
        self::assertSame(422, $this->call('POST', '/api/v1/admin/media/' . self::BROCHURE . '/resource', ['listed' => 'yes'], cookie: $admin)->status());
        self::assertSame(422, $this->call('POST', '/api/v1/admin/media/' . self::PICTURE . '/resource', ['listed' => true, 'category' => 'other', 'summary' => 'Pictures are not resources.'], cookie: $admin)->status(), 'documents only');
        self::assertSame(404, $this->call('POST', '/api/v1/admin/media/00000000-0000-4000-8000-000000000000/resource', ['listed' => true], cookie: $admin)->status());

        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD], cookie: $admin);
        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));
        self::assertSame(403, $this->call('POST', '/api/v1/admin/media/' . self::GUIDE . '/resource', ['listed' => true, 'category' => 'guide', 'summary' => 'Everything applicants need to know.'], cookie: $bd)->status());
        self::assertFalse(self::decode($this->call('GET', '/api/v1/admin/media', cookie: $bd))['data'][0]['resource']['listed'], 'Business Development sees the listing state');
        self::assertSame([], $this->publicList()['data']);
    }

    /** @return array<string, mixed> */
    private function publicList(): array
    {
        return self::decode($this->app()->handle(self::request('GET', '/api/v1/resources')));
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
