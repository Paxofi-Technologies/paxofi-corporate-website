<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

/** What an uploaded file really is, judged from its content (not its name). */
final readonly class DetectedFile
{
    public function __construct(
        public MediaKind $kind,
        public string $mimeType,
        public string $extension,
    ) {
    }
}
