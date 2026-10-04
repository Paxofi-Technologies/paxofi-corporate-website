<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use InvalidArgumentException;

/**
 * A plain-text email (decision D-016), with optional file attachments
 * (D-019). Header values are single lines, so nothing a visitor types can add
 * headers or recipients.
 */
final readonly class Email
{
    /** @var list<string> */
    public array $to;
    public string $subject;
    public ?string $replyTo;

    /**
     * @param list<string> $to
     * @param list<Attachment> $attachments
     */
    public function __construct(
        array $to,
        string $subject,
        public string $text,
        ?string $replyTo = null,
        public string $kind = 'general',
        public array $attachments = [],
    ) {
        $to = array_values(array_filter($to, static fn (string $address): bool => self::isAddress($address)));
        if ($to === []) {
            throw new InvalidArgumentException('An email needs at least one valid recipient.');
        }
        $this->to = $to;
        $this->subject = mb_substr(self::oneLine($subject), 0, 200);
        $this->replyTo = $replyTo !== null && self::isAddress($replyTo) ? $replyTo : null;
    }

    public static function isAddress(string $address): bool
    {
        return strlen($address) <= 254 && preg_match('/[\r\n<>,;"]/', $address) !== 1 && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return list<string> valid addresses from a comma-separated setting */
    public static function addressList(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), self::isAddress(...)));
    }

    public static function oneLine(string $value): string
    {
        return trim((string) preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', mb_scrub($value, 'UTF-8')));
    }
}
