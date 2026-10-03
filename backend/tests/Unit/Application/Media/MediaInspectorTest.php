<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application\Media;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Media\MediaInspector;
use Paxofi\CorporateWebsite\Application\Media\MediaKind;
use Paxofi\CorporateWebsite\Http\RequestFactory;
use Paxofi\CorporateWebsite\Infrastructure\Media\GdImageProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaInspectorTest extends TestCase
{
    public function testTheContentDecidesTheTypeNotTheName(): void
    {
        $inspector = new MediaInspector();

        $png = $inspector->inspect("\x89PNG\r\n\x1A\n rest", 'photo.jpg');
        self::assertSame([MediaKind::Image, 'image/png', 'png'], [$png->kind, $png->mimeType, $png->extension]);

        $webp = $inspector->inspect('RIFF' . "\0\0\0\0" . 'WEBPVP8 ', 'x');
        self::assertSame('image/webp', $webp->mimeType);

        $pdf = $inspector->inspect("%PDF-1.7\n", 'brochure.PDF');
        self::assertSame([MediaKind::Document, 'application/pdf'], [$pdf->kind, $pdf->mimeType]);

        self::assertSame('text/plain', $inspector->inspect("Plain notes\n", 'notes.txt')->mimeType);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function refused(): iterable
    {
        yield 'gif' => ['GIF89a....', 'anim.gif', 'GIF images'];
        yield 'heic' => ["\0\0\0\x18ftypheic....", 'IMG_0001.HEIC', 'HEIC'];
        yield 'old office' => ["\xD0\xCF\x11\xE0 legacy", 'report.doc', 'Older Office files'];
        yield 'binary text' => ["a\0b", 'data.csv', 'not accepted'];
        yield 'html' => ['<html><script>1</script></html>', 'page.html', 'not accepted'];
        yield 'zip that is not office' => ["PK\x03\x04 archive", 'archive.zip', 'not accepted'];
    }

    #[DataProvider('refused')]
    public function testOtherFilesAreRefusedWithAHelpfulMessage(string $bytes, string $name, string $message): void
    {
        try {
            (new MediaInspector())->inspect($bytes, $name);
            self::fail('accepted ' . $name);
        } catch (ValidationFailed $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertArrayHasKey('file', $exception->fieldErrors());
        }
    }

    public function testDownloadNamesAreSafe(): void
    {
        self::assertSame('Cafe-menu-2026.pdf', MediaInspector::safeFilename('Café menu (2026).final', 'pdf'));
        self::assertSame('passwd.txt', MediaInspector::safeFilename('../../etc/passwd', 'txt'));
        self::assertSame('file.png', MediaInspector::safeFilename('???.png', 'png'));
        self::assertSame(84, strlen(MediaInspector::safeFilename(str_repeat('a', 300), 'pdf')));
    }

    public function testJpegOrientationIsReadFromExif(): void
    {
        $tiff = "II\x2A\x00\x08\x00\x00\x00" . "\x01\x00" . "\x12\x01\x03\x00\x01\x00\x00\x00\x08\x00\x00\x00" . "\x00\x00\x00\x00";
        $exif = "Exif\x00\x00" . $tiff;
        $jpeg = "\xFF\xD8" . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . "\xFF\xDA\x00\x02";

        self::assertSame(8, GdImageProcessor::jpegOrientation($jpeg));
        self::assertSame(1, GdImageProcessor::jpegOrientation("\xFF\xD8\xFF\xDA\x00\x02"), 'no EXIF: upright');
    }

    public function testOnlyTheUploadRouteReadsLargeBodies(): void
    {
        self::assertSame(10 * 1024 * 1024, RequestFactory::bodyLimit(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/admin/media?filename=a.pdf']));
        self::assertSame(65536, RequestFactory::bodyLimit(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/forms/contact/submit']));
        self::assertSame(65536, RequestFactory::bodyLimit(['REQUEST_METHOD' => 'PATCH', 'REQUEST_URI' => '/api/v1/admin/media/x']));
    }
}
