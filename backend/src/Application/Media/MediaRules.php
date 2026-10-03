<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

/** Upload limits for the media library (decision D-012). */
final class MediaRules
{
    public const IMAGE_MAX_BYTES = 5 * 1024 * 1024;
    public const DOCUMENT_MAX_BYTES = 10 * 1024 * 1024;
    /** Largest upload of any kind; the API reads request bodies up to this size on the upload route only. */
    public const UPLOAD_MAX_BYTES = self::DOCUMENT_MAX_BYTES;
    /** Images larger than this (in pixels) are refused before decoding, to bound memory use. */
    public const IMAGE_MAX_PIXELS = 25_000_000;
    /** Longest side of a stored image; larger images are scaled down. */
    public const IMAGE_MAX_EDGE = 2400;

    /** @var array<string, array{mime: string, label: string}> document extension => type */
    public const DOCUMENT_TYPES = [
        'pdf' => ['mime' => 'application/pdf', 'label' => 'PDF'],
        'docx' => ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'label' => 'Word'],
        'xlsx' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'label' => 'Excel'],
        'pptx' => ['mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'label' => 'PowerPoint'],
        'txt' => ['mime' => 'text/plain', 'label' => 'Text'],
        'csv' => ['mime' => 'text/csv', 'label' => 'CSV'],
    ];

    /** @var array<string, string> image MIME type => extension */
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** Short format name shown next to download links, e.g. "PDF". */
    public static function formatLabel(string $mimeType): string
    {
        foreach (self::DOCUMENT_TYPES as $type) {
            if ($type['mime'] === $mimeType) {
                return $type['label'];
            }
        }

        return strtoupper(self::IMAGE_TYPES[$mimeType] ?? 'file');
    }
}
