<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Media;

use GdImage;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Media\ImageProcessor;
use Paxofi\CorporateWebsite\Application\Media\MediaRules;
use Paxofi\CorporateWebsite\Application\Media\ProcessedImage;

/**
 * Re-encodes images with PHP's GD extension. Decoding and encoding again
 * keeps only the pixels: camera details and location are dropped, and any
 * data hidden in the file is discarded. Phone photos are turned upright from
 * their EXIF orientation first, since that tag is removed too.
 */
final class GdImageProcessor implements ImageProcessor
{
    private const QUALITY = 85;

    /** Null when the server has no GD (images then cannot be uploaded). */
    public static function create(): ?self
    {
        return function_exists('imagecreatefromstring') ? new self() : null;
    }

    public function process(string $bytes, string $mimeType): ProcessedImage
    {
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw self::invalid('The picture could not be read. It may be damaged; save it again and retry.');
        }
        if ($size[0] * $size[1] > MediaRules::IMAGE_MAX_PIXELS) {
            throw self::invalid('The picture has too many pixels (most 25 megapixels). Make it smaller and try again.');
        }
        if ($mimeType === 'image/webp' && !function_exists('imagewebp')) {
            throw self::invalid('The server cannot process WebP images. Use JPEG or PNG.');
        }

        self::ensureMemory();
        $image = @imagecreatefromstring($bytes);
        if (!$image instanceof GdImage) {
            throw self::invalid('The picture could not be read. It may be damaged; save it again and retry.');
        }

        if ($mimeType === 'image/jpeg') {
            $image = self::upright($image, self::jpegOrientation($bytes));
        }
        $image = self::limitSize($image, $mimeType !== 'image/jpeg');

        if ($mimeType === 'image/jpeg') {
            imageinterlace($image, true);
        }
        ob_start();
        $ok = match ($mimeType) {
            'image/png' => imagepng($image, null, 9),
            'image/webp' => imagewebp($image, null, self::QUALITY),
            default => imagejpeg($image, null, self::QUALITY),
        };
        $encoded = (string) ob_get_clean();
        $width = imagesx($image);
        $height = imagesy($image);
        if (!$ok || $encoded === '') {
            throw self::invalid('The picture could not be processed. Save it again as JPEG or PNG and retry.');
        }

        return new ProcessedImage($encoded, $mimeType, $width, $height);
    }

    /** Scales the image down so its longest side is at most IMAGE_MAX_EDGE. */
    private static function limitSize(GdImage $image, bool $keepTransparency): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);
        if ($keepTransparency) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        if ($longest <= MediaRules::IMAGE_MAX_EDGE) {
            return $image;
        }

        $scale = MediaRules::IMAGE_MAX_EDGE / $longest;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if ($keepTransparency) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));
        }
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    /** Applies an EXIF orientation (1–8) so the stored pixels are upright. */
    private static function upright(GdImage $image, int $orientation): GdImage
    {
        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        $rotated = $rotated instanceof GdImage ? $rotated : $image;
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    /** Reads the EXIF orientation tag (0x0112) from a JPEG's APP1 segment; 1 when absent. */
    public static function jpegOrientation(string $bytes): int
    {
        $offset = 2;
        $length = strlen($bytes);
        while ($offset + 4 <= $length && $bytes[$offset] === "\xFF") {
            $marker = ord($bytes[$offset + 1]);
            $segment = unpack('n', substr($bytes, $offset + 2, 2))[1];
            if ($marker === 0xDA || $segment < 2) {
                break;
            }
            if ($marker === 0xE1 && substr($bytes, $offset + 4, 6) === "Exif\0\0") {
                return self::tiffOrientation(substr($bytes, $offset + 10, $segment - 8));
            }
            $offset += 2 + $segment;
        }

        return 1;
    }

    private static function tiffOrientation(string $tiff): int
    {
        $little = substr($tiff, 0, 2) === 'II';
        if (!$little && substr($tiff, 0, 2) !== 'MM') {
            return 1;
        }
        $short = static fn (int $at): int => strlen($tiff) >= $at + 2 ? unpack($little ? 'v' : 'n', substr($tiff, $at, 2))[1] : 0;
        $long = static fn (int $at): int => strlen($tiff) >= $at + 4 ? unpack($little ? 'V' : 'N', substr($tiff, $at, 4))[1] : 0;

        $ifd = $long(4);
        $entries = $short($ifd);
        for ($i = 0; $i < $entries && $i < 256; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($short($entry) === 0x0112) {
                $value = $short($entry + 8);

                return $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }

    /** Decoding needs about 5 bytes per pixel; raise a low memory_limit for this request only. */
    private static function ensureMemory(): void
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '-1') {
            return;
        }
        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        if ($bytes < 384 * 1024 ** 2) {
            @ini_set('memory_limit', '384M');
        }
    }

    private static function invalid(string $message): ValidationFailed
    {
        return new ValidationFailed(['file' => $message], $message);
    }
}
