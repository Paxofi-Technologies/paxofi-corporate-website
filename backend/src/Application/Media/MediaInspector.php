<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use ZipArchive;

/**
 * Decides what an uploaded file is from its content, and refuses anything the
 * website should not host (decision D-012):
 *  - images: JPEG, PNG, WebP (not SVG, which can carry script);
 *  - documents: PDF, Word/Excel/PowerPoint (.docx/.xlsx/.pptx, without macros),
 *    plain text and CSV (UTF-8).
 * The file name only chooses between formats that share a container
 * (the Office formats are all ZIP files; text and CSV are both text).
 */
final class MediaInspector
{
    public const NOT_ACCEPTED = 'This type of file is not accepted. Use a JPEG, PNG or WebP image, or a PDF, Word (.docx), Excel (.xlsx), PowerPoint (.pptx), text or CSV document.';

    /** Office formats: the folder each must contain. */
    private const OFFICE_PARTS = ['docx' => 'word/', 'xlsx' => 'xl/', 'pptx' => 'ppt/'];

    public function inspect(string $bytes, string $filename): DetectedFile
    {
        if ($bytes === '') {
            throw self::invalid('The file is empty.');
        }
        $extension = self::extension($filename);

        $image = self::imageType($bytes);
        if ($image !== null) {
            if (strlen($bytes) > MediaRules::IMAGE_MAX_BYTES) {
                throw self::invalid('Images can be up to 5 MB. Make the picture smaller and try again.');
            }

            return new DetectedFile(MediaKind::Image, $image, MediaRules::IMAGE_TYPES[$image]);
        }

        if (strlen($bytes) > MediaRules::DOCUMENT_MAX_BYTES) {
            throw self::invalid('Documents can be up to 10 MB.');
        }
        if (str_starts_with($bytes, '%PDF-')) {
            return self::document('pdf');
        }
        if (str_starts_with($bytes, "PK\x03\x04")) {
            return self::document(self::officeType($bytes, $extension));
        }
        if (in_array($extension, ['txt', 'csv'], true) && self::isText($bytes)) {
            return self::document($extension);
        }

        throw self::invalid(match (true) {
            str_starts_with($bytes, 'GIF8') => 'GIF images are not accepted. Save the picture as PNG or JPEG.',
            self::isHeic($bytes) => 'iPhone HEIC photos are not accepted. Export the photo as JPEG (or set the camera to "Most Compatible") and try again.',
            in_array($extension, ['svg', 'svgz'], true) => 'SVG images are not accepted, because they can contain code. Save the picture as PNG.',
            in_array($extension, ['doc', 'xls', 'ppt', 'docm', 'xlsm', 'pptm'], true) => 'Older Office files and files with macros are not accepted. Save it as .docx, .xlsx, .pptx or PDF.',
            default => self::NOT_ACCEPTED,
        });
    }

    /** A safe download name: letters, digits, dot, dash and underscore, with the detected extension. */
    public static function safeFilename(string $original, string $extension): string
    {
        $base = pathinfo(str_replace('\\', '/', $original), PATHINFO_FILENAME);
        $ascii = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: $base) : $base;
        $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $ascii), '-_');
        $base = substr($base === '' ? 'file' : $base, 0, 80);

        return $base . '.' . $extension;
    }

    private static function imageType(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($bytes, "\x89PNG\r\n\x1A\n") => 'image/png',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }

    /** .docx/.xlsx/.pptx: the name chooses the format; the content must match it and carry no macros. */
    private static function officeType(string $bytes, string $extension): string
    {
        if (!isset(self::OFFICE_PARTS[$extension])) {
            throw self::invalid(self::NOT_ACCEPTED);
        }
        if (!class_exists(ZipArchive::class)) {
            // Without the zip extension only the container is checked.
            return $extension;
        }

        $path = tempnam(sys_get_temp_dir(), 'pxm');
        if ($path === false) {
            return $extension;
        }
        try {
            file_put_contents($path, $bytes);
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::RDONLY) !== true) {
                throw self::invalid('The file is damaged or not a real ' . MediaRules::DOCUMENT_TYPES[$extension]['label'] . ' file.');
            }
            $hasTypes = false;
            $hasPart = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $hasTypes = $hasTypes || $name === '[Content_Types].xml';
                $hasPart = $hasPart || str_starts_with($name, self::OFFICE_PARTS[$extension]);
                if (stripos($name, 'vbaProject.bin') !== false) {
                    $zip->close();
                    throw self::invalid('Files with macros are not accepted. Save it without macros (.docx, .xlsx or .pptx) or as PDF.');
                }
            }
            $zip->close();
            if (!$hasTypes || !$hasPart) {
                throw self::invalid('The file is damaged or not a real ' . MediaRules::DOCUMENT_TYPES[$extension]['label'] . ' file.');
            }
        } finally {
            @unlink($path);
        }

        return $extension;
    }

    private static function isText(string $bytes): bool
    {
        return !str_contains($bytes, "\0") && mb_check_encoding($bytes, 'UTF-8');
    }

    private static function isHeic(string $bytes): bool
    {
        return substr($bytes, 4, 4) === 'ftyp' && in_array(substr($bytes, 8, 4), ['heic', 'heix', 'mif1', 'msf1', 'hevc'], true);
    }

    private static function document(string $extension): DetectedFile
    {
        return new DetectedFile(MediaKind::Document, MediaRules::DOCUMENT_TYPES[$extension]['mime'], $extension);
    }

    private static function extension(string $filename): string
    {
        return strtolower(pathinfo(str_replace('\\', '/', $filename), PATHINFO_EXTENSION));
    }

    private static function invalid(string $message): ValidationFailed
    {
        return new ValidationFailed(['file' => $message], $message);
    }
}
