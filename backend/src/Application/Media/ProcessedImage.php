<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

/** An image re-encoded for the website: metadata removed, size limited. */
final readonly class ProcessedImage
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {
    }
}
