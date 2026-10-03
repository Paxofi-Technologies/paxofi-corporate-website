<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

interface ImageProcessor
{
    /**
     * Decodes and re-encodes the image in the same format: drops metadata
     * (camera, location), applies the camera's rotation, and shrinks it to at
     * most MediaRules::IMAGE_MAX_EDGE pixels on its longest side.
     *
     * @throws ValidationFailed when the image cannot be read or is too large in pixels
     */
    public function process(string $bytes, string $mimeType): ProcessedImage;
}
