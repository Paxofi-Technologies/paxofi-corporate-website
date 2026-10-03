<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

use DateTimeImmutable;

/**
 * Time-based one-time passwords (RFC 6238: HMAC-SHA1, 6 digits, 30-second steps),
 * the format every authenticator app understands.
 */
final class Totp
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    /** Accepts the previous and next step as well, for clock drift. */
    private const WINDOW = 1;

    public static function generateSecret(): string
    {
        return random_bytes(20);
    }

    public static function step(DateTimeImmutable $time): int
    {
        return intdiv($time->getTimestamp(), self::PERIOD);
    }

    public static function code(string $secret, int $step, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), $secret, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the matching step, or null. A step at or before $lastUsedStep is
     * refused, so a code cannot be used twice.
     */
    public static function verify(string $secret, string $code, DateTimeImmutable $now, ?int $lastUsedStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }
        $current = self::step($now);
        for ($step = $current - self::WINDOW; $step <= $current + self::WINDOW; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** otpauth:// link for the QR code an authenticator app scans. */
    public static function provisioningUri(string $secret, string $account, string $issuer = 'Paxofi'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            Base32::encode($secret),
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }
}
