<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

/**
 * Who emails go to, and the staff area address used in links (D-016).
 * Sending is on only when a transport is configured (MAIL_TRANSPORT).
 */
final readonly class MailSettings
{
    /**
     * @param list<string> $enquiryAlertTo
     * @param list<string> $errorAlertTo
     */
    public function __construct(
        public bool $enabled,
        public array $enquiryAlertTo,
        public array $errorAlertTo,
        public string $siteUrl,
        public string $siteName = 'Paxofi Technologies',
    ) {
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
