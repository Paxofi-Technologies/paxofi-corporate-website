<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Mail;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Mail\AlertThrottle;

/** Remembers when each alert last went out as a small file's modification time. */
final class FileAlertThrottle implements AlertThrottle
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? sys_get_temp_dir() . '/paxofi-alerts-' . substr(hash('sha256', __DIR__), 0, 12), '/');
    }

    public function allow(string $fingerprint, DateTimeImmutable $now, int $seconds): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return true;
        }
        $file = $this->directory . '/' . preg_replace('/[^a-f0-9]/', '', $fingerprint);
        $last = @filemtime($file);
        if ($last !== false && $now->getTimestamp() - $last < $seconds) {
            return false;
        }
        @touch($file, $now->getTimestamp());

        return true;
    }
}
