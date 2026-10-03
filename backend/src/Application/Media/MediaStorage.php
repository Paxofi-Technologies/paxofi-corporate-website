<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

/** Where uploaded files are kept; each file is named by its media id. */
interface MediaStorage
{
    /** True when files can be stored (the folder exists and is writable). */
    public function available(): bool;

    public function put(string $reference, string $bytes): void;

    public function get(string $reference): ?string;

    public function delete(string $reference): void;
}
