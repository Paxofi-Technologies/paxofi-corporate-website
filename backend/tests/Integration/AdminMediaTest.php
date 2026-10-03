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
use ZipArchive;

/** The media library and pictures/documents on products and services (decision D-012), against a real MariaDB. */
final class AdminMediaTest extends DatabaseTestCase
{
    use HttpRequests;

    private const ORIGIN = 'https://corporate.paxofi.com';
    private const SETUP_TOKEN = 'setup-token-0123456789-abcdefghijklmnop';
    private const PAY = '7b0f3a0e-5c1d-4f6a-9b8e-1a2c3d4e5f01';
    private const BD_PASSWORD = 'Temporary pass phrase 7';

    /** @var array<string, list<array<string, mixed>>> */
    private static array $seed = [];
    private static string $storage;

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('The PHP gd extension is needed to make test images.');
        }
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
        foreach (self::$seed as $table => $rows) {
            self::$pdo->exec("DELETE FROM {$table}");
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $insert = self::$pdo->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', $columns), implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))));
                $insert->execute($row);
            }
        }
        self::$pdo->exec('DELETE FROM media_assets');
        self::$pdo->exec('DELETE FROM users');

        self::$storage = sys_get_temp_dir() . '/paxofi-media-test-' . bin2hex(random_bytes(4));
        mkdir(self::$storage);
    }

    protected function tearDown(): void
    {
        foreach (glob(self::$storage . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir(self::$storage);
    }

    public function testImagesAreCleanedOnUploadAndServedWithSafeHeaders(): void
    {
        $admin = $this->setupAdmin();
        $photo = self::jpegWithMetadata(40, 20, orientation: 6, comment: 'SECRET-LOCATION 51.5N 0.1W');

        $response = $this->upload($admin, $photo, 'Team photo (2026).JPG', ['alt_text' => 'The Paxofi team at the office']);
        self::assertSame(201, $response->status(), $response->body());
        $image = self::decode($response)['data'];
        self::assertSame('image', $image['kind']);
        self::assertSame('Team-photo-2026.jpg', $image['filename']);
        self::assertSame([20, 40], [$image['width'], $image['height']], 'turned upright from the camera orientation');
        self::assertSame('Samuel Adeniji', $image['uploaded_by']);
        self::assertSame([], $image['used_by']);

        $stored = (string) file_get_contents(self::$storage . '/' . $image['id']);
        self::assertStringNotContainsString('SECRET-LOCATION', $stored, 'comments and metadata are removed');
        self::assertStringNotContainsString('Exif', $stored);
        self::assertFileExists(self::$storage . '/.htaccess');

        $served = $this->app()->handle(self::request('GET', $image['path']));
        self::assertSame(200, $served->status());
        self::assertSame($stored, $served->body());
        self::assertSame('image/jpeg', $served->header('content-type'));
        self::assertStringStartsWith('inline;', (string) $served->header('content-disposition'));
        self::assertSame('public, max-age=31536000, immutable', $served->header('cache-control'));
        self::assertSame('nosniff', $served->header('x-content-type-options'));
        $notModified = $this->app()->handle(self::request('GET', $image['path'], ['if-none-match' => (string) $served->header('etag')]));
        self::assertSame(304, $notModified->status());

        $wide = $this->upload($admin, self::png(3000, 100), 'banner.png', ['alt_text' => 'A wide banner']);
        self::assertSame([2400, 80], [self::decode($wide)['data']['width'], self::decode($wide)['data']['height']], 'scaled down to 2400 pixels');

        self::assertSame(404, $this->app()->handle(self::request('GET', '/api/v1/media/00000000-0000-4000-8000-000000000000/x.jpg'))->status());
        self::assertSame(['media.uploaded'], array_values(array_unique(self::$pdo->query("SELECT action FROM audit_events WHERE target_type = 'media'")->fetchAll(\PDO::FETCH_COLUMN))));
    }

    public function testDocumentsAreCheckedAndAlwaysDownloaded(): void
    {
        $admin = $this->setupAdmin();

        $pdf = $this->upload($admin, "%PDF-1.4\n1 0 obj << >> endobj\n%%EOF\n", 'Paxofi Pay brochure.pdf');
        self::assertSame(201, $pdf->status(), $pdf->body());
        $document = self::decode($pdf)['data'];
        self::assertSame(['document', 'PDF', 'Paxofi Pay brochure'], [$document['kind'], $document['format'], $document['title']], 'title defaults to the file name');
        $served = $this->app()->handle(self::request('GET', $document['path']));
        self::assertSame('application/pdf', $served->header('content-type'));
        self::assertSame('attachment; filename="Paxofi-Pay-brochure.pdf"; filename*=UTF-8\'\'Paxofi-Pay-brochure.pdf', $served->header('content-disposition'));

        $word = $this->upload($admin, self::office(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>']), 'Proposal.docx', ['title' => 'Proposal template']);
        self::assertSame(201, $word->status(), $word->body());
        self::assertSame('Word', self::decode($word)['data']['format']);

        $csv = $this->upload($admin, "name,price\nPay,0\n", 'prices.csv', ['title' => 'Prices']);
        self::assertSame('text/csv; charset=utf-8', $this->app()->handle(self::request('GET', self::decode($csv)['data']['path']))->header('content-type'));

        $refused = [
            'macros' => [self::office(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>', 'word/vbaProject.bin' => 'x']), 'Invoice.docx'],
            'program' => ["MZ\x90\x00 not a document", 'setup.exe'],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.svg'],
            'disguised' => ['<?php echo 1;', 'notes.pdf'],
            'mismatched office' => [self::office(['[Content_Types].xml' => '<Types/>', 'xl/workbook.xml' => '<w/>']), 'Report.docx'],
            'empty' => ['', 'empty.pdf'],
        ];
        foreach ($refused as $case => [$bytes, $name]) {
            $response = $this->upload($admin, $bytes, $name, ['title' => 'Something']);
            self::assertSame(422, $response->status(), $case);
            self::assertArrayHasKey('file', self::decode($response)['error']['details']['fields'], $case);
        }

        $noAlt = $this->upload($admin, self::png(10, 10), 'chart.png');
        self::assertSame(422, $noAlt->status());
        self::assertArrayHasKey('alt_text', self::decode($noAlt)['error']['details']['fields'], 'pictures need a description');

        $tooBig = $this->upload($admin, '%PDF-' . str_repeat('x', 10 * 1024 * 1024), 'huge.pdf');
        self::assertSame(422, $tooBig->status());

        $list = self::decode($this->call('GET', '/api/v1/admin/media?kind=document', cookie: $admin));
        self::assertCount(3, $list['data']);
        self::assertTrue($list['meta']['uploads']);
        self::assertSame(10 * 1024 * 1024, $list['meta']['document_max_bytes']);

        $renamed = self::decode($this->call('PATCH', '/api/v1/admin/media/' . $document['id'], ['title' => 'Paxofi Pay overview'], cookie: $admin))['data'];
        self::assertSame('Paxofi Pay overview', $renamed['title']);
        self::assertSame(422, $this->call('PATCH', '/api/v1/admin/media/' . $document['id'], ['title' => ''], cookie: $admin)->status());
    }

    public function testItemsShowChosenMediaAndFilesInUseCannotBeDeleted(): void
    {
        $admin = $this->setupAdmin();
        $image = self::decode($this->upload($admin, self::png(800, 600), 'pay.png', ['alt_text' => 'Paxofi Pay on a phone']))['data'];
        $document = self::decode($this->upload($admin, "%PDF-1.7\n%%EOF", 'pay.pdf', ['title' => 'Paxofi Pay brochure']))['data'];

        $wrongKind = $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(['image_id' => $document['id'], 'document_id' => '00000000-0000-4000-8000-000000000000']), cookie: $admin);
        self::assertSame(422, $wrongKind->status());
        self::assertEqualsCanonicalizing(['image_id', 'document_id'], array_keys(self::decode($wrongKind)['error']['details']['fields']));

        $draft = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(['image_id' => $image['id'], 'document_id' => $document['id']]), cookie: $admin))['data'];
        self::assertSame($image['id'], $draft['draft']['content']['image_id']);
        self::assertNull($this->publicItem('paxofi-pay')['image'], 'not public before publishing');

        $inDraft = $this->call('DELETE', '/api/v1/admin/media/' . $image['id'], cookie: $admin);
        self::assertSame(409, $inDraft->status());
        self::assertStringContainsString('Paxofi Pay (product draft)', self::decode($inDraft)['error']['message']);

        $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin);
        $public = $this->publicItem('paxofi-pay');
        self::assertSame(['path' => $image['path'], 'alt' => 'Paxofi Pay on a phone', 'width' => 800, 'height' => 600], $public['image']);
        self::assertSame(['path' => $document['path'], 'title' => 'Paxofi Pay brochure', 'format' => 'PDF', 'size_bytes' => strlen("%PDF-1.7\n%%EOF")], $public['document']);

        $media = self::decode($this->call('GET', '/api/v1/admin/media', cookie: $admin))['data'];
        self::assertSame(['Paxofi Pay (product)'], array_column($media, 'used_by', 'id')[$image['id']]);

        $this->call('POST', '/api/v1/admin/users', ['email' => 'bd@paxofi.com', 'display_name' => 'Business Dev', 'role' => 'business_development', 'password' => self::BD_PASSWORD], cookie: $admin);
        $bd = $this->token($this->call('POST', '/api/v1/admin/session', ['email' => 'bd@paxofi.com', 'password' => self::BD_PASSWORD]));
        self::assertSame(201, $this->upload($bd, self::png(10, 10), 'icon.png', ['alt_text' => 'An icon'])->status(), 'business development can upload');
        self::assertSame(403, $this->call('DELETE', '/api/v1/admin/media/' . $document['id'], cookie: $bd)->status(), 'only an administrator deletes');

        $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/draft', $this->content(), cookie: $admin);
        $this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . '/publish', cookie: $admin);
        self::assertNull($this->publicItem('paxofi-pay')['image']);
        self::assertSame(200, $this->call('DELETE', '/api/v1/admin/media/' . $image['id'], cookie: $admin)->status());
        self::assertFileDoesNotExist(self::$storage . '/' . $image['id']);
        self::assertSame(404, $this->app()->handle(self::request('GET', $image['path']))->status());

        $history = self::decode($this->call('GET', '/api/v1/admin/catalog/products/' . self::PAY, cookie: $admin))['data']['revisions'];
        $withImage = array_values(array_filter($history, static fn (array $r): bool => $r['content']['image_id'] !== null))[0];
        $restored = self::decode($this->call('POST', '/api/v1/admin/catalog/products/' . self::PAY . "/revisions/{$withImage['id']}/restore", cookie: $admin))['data'];
        self::assertNull($restored['draft']['content']['image_id'], 'a deleted picture is left out when restoring');
        self::assertSame($document['id'], $restored['draft']['content']['document_id']);
    }

    public function testUploadsAreRefusedUntilConfiguredAndFromOtherSites(): void
    {
        $admin = $this->setupAdmin();
        $unconfigured = $this->app(storage: false);
        $list = self::decode($unconfigured->handle($this->staffRequest('GET', '/api/v1/admin/media', [], $admin)));
        self::assertFalse($list['meta']['uploads']);
        $refused = $unconfigured->handle($this->staffRequest('POST', '/api/v1/admin/media?filename=a.pdf&title=Brochure', [], $admin, '%PDF-1.4'));
        self::assertSame(503, $refused->status());
        self::assertStringContainsString('MEDIA_STORAGE_PATH', self::decode($refused)['error']['message']);

        $crossSite = $this->app()->handle($this->staffRequest('POST', '/api/v1/admin/media?filename=a.pdf', ['origin' => 'https://evil.example'], $admin, '%PDF-1.4'));
        self::assertSame(403, $crossSite->status());
        self::assertSame(401, $this->app()->handle(self::request('POST', '/api/v1/admin/media?filename=a.pdf', ['origin' => self::ORIGIN], '%PDF-1.4'))->status());

        $dropped = $this->app()->handle($this->staffRequest('POST', '/api/v1/admin/media?filename=a.pdf', ['content-length' => '20000000'], $admin, ''));
        self::assertSame(422, $dropped->status());
        self::assertStringContainsString('post_max_size', self::decode($dropped)['error']['message'], 'PHP dropped the body: say which setting to raise');
    }

    private function upload(string $cookie, string $bytes, string $filename, array $details = []): HttpResponse
    {
        $query = http_build_query(['filename' => $filename] + $details);

        return $this->app()->handle($this->staffRequest('POST', '/api/v1/admin/media?' . $query, [], $cookie, $bytes));
    }

    /** @param array<string, string> $headers */
    private function staffRequest(string $method, string $uri, array $headers, string $cookie, string $body = ''): \Paxofi\Core\Contracts\HttpRequest
    {
        return self::request($method, $uri, $headers + [
            'origin' => self::ORIGIN,
            'cookie' => 'paxofi_admin=' . $cookie,
            'content-type' => 'application/octet-stream',
            'content-length' => (string) strlen($body),
        ], $body);
    }

    /** A JPEG with an EXIF orientation tag and a comment, as phones produce. */
    private static function jpegWithMetadata(int $width, int $height, int $orientation, string $comment): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $tiff = "MM\x00\x2A\x00\x00\x00\x08" . "\x00\x01" . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00" . "\x00\x00\x00\x00";
        $exif = "Exif\x00\x00" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;
        $com = "\xFF\xFE" . pack('n', strlen($comment) + 2) . $comment;

        return substr($jpeg, 0, 2) . $app1 . $com . substr($jpeg, 2);
    }

    private static function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** @param array<string, string> $entries */
    private static function office(array $entries): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    /** @return array<string, mixed> */
    private function content(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Paxofi Pay',
            'label' => 'Paxofi Product',
            'icon' => 'shield-check',
            'summary' => 'Digital payments infrastructure designed around reliability.',
            'points' => ['Transaction certainty'],
            'sort_order' => 10,
        ];
    }

    /** @return array<string, mixed> */
    private function publicItem(string $slug): array
    {
        return self::decode($this->app()->handle(self::request('GET', "/api/v1/products?slug={$slug}")))['data'][0];
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

    private function app(bool $storage = true): ApiApplication
    {
        $environment = Environment::from([
            'APP_ENV' => 'testing',
            'DB_DATABASE' => (string) self::$environment->get('DB_DATABASE'),
            'CORS_ALLOWED_ORIGINS' => self::ORIGIN,
            'ADMIN_SETUP_TOKEN' => self::SETUP_TOKEN,
        ] + ($storage ? ['MEDIA_STORAGE_PATH' => self::$storage] : []));
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
