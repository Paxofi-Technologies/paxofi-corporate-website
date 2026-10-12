<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use Closure;

/**
 * Who emails go to, and the staff area address used in links (D-016).
 * Sending is on only when a transport is configured (MAIL_* in .env, or the
 * staff area's Email page, D-031).
 *
 * $enabled may be a closure: it is then decided the first time it is read,
 * so a request that never sends email never reads the saved settings.
 *
 * @property-read bool $enabled
 */
final class MailSettings
{
    /** @var bool|Closure(): bool */
    private bool|Closure $enabled;

    /**
     * @param bool|Closure(): bool $enabled
     * @param list<string> $enquiryAlertTo
     * @param list<string> $errorAlertTo
     */
    public function __construct(
        bool|Closure $enabled,
        public readonly array $enquiryAlertTo,
        public readonly array $errorAlertTo,
        public readonly string $siteUrl,
        public readonly string $siteName = 'Paxofi Technologies',
    ) {
        $this->enabled = $enabled;
    }

    public function __get(string $name): bool
    {
        if ($name !== 'enabled') {
            throw new \LogicException("MailSettings has no property {$name}.");
        }
        if ($this->enabled instanceof Closure) {
            $this->enabled = (bool) ($this->enabled)();
        }

        return $this->enabled;
    }

    public function __isset(string $name): bool
    {
        return $name === 'enabled';
    }

    public static function disabled(): self
    {
        return new self(false, [], [], 'https://corporate.paxofi.com');
    }

    public function staffLink(string $path): string
    {
        return rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/');
    }
}
