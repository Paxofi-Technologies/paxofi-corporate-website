<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

/** Public address of a file, relative to the API host: /api/v1/media/{id}/{filename}. */
final class MediaPath
{
    public static function for(string $id, string $filename): string
    {
        return '/api/v1/media/' . $id . '/' . rawurlencode($filename);
    }
}
