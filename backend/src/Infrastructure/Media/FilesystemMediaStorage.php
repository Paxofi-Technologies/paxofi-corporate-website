<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Media;

use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Media\MediaStorage;

/**
 * Files in one folder outside the website and API release folders
 * (MEDIA_STORAGE_PATH), so a new release never removes them. Each file is
 * named by its media id, with no extension, and is only ever sent through the
 * API's download route.
 */
final class FilesystemMediaStorage implements MediaStorage
{
    private readonly ?string $directory;

    public function __construct(?string $directory)
    {
        $directory = $directory === null ? '' : rtrim(trim($directory), '/\\');
        $this->directory = $directory === '' ? null : $directory;
    }

    public function available(): bool
    {
        return $this->directory !== null && is_dir($this->directory) && is_writable($this->directory);
    }

    public function put(string $reference, string $bytes): void
    {
        $path = $this->path($reference);
        $this->protectFolder();
        $temporary = $path . '.part';
        if (@file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !@rename($temporary, $path)) {
            @unlink($temporary);
            throw new DependencyUnavailable('The file could not be saved on the server. Check that the media folder has free space and can be written to.');
        }
        @chmod($path, 0640);
    }

    public function get(string $reference): ?string
    {
        $path = $this->path($reference);
        if (!is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function delete(string $reference): void
    {
        $path = $this->path($reference);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function path(string $reference): string
    {
        if ($this->directory === null || preg_match('/^[0-9a-f-]{36}$/', $reference) !== 1) {
            throw new DependencyUnavailable('File uploads are not set up on the server yet (MEDIA_STORAGE_PATH).');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $reference;
    }

    /** Defence in depth: if the folder is ever placed inside a website, Apache refuses to serve it. */
    private function protectFolder(): void
    {
        $htaccess = $this->directory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
    }
}
