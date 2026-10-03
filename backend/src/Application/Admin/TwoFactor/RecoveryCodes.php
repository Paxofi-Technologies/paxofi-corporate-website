<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

/**
 * One-time recovery codes for when the authenticator is lost: ten codes of
 * ten characters (50 bits each), shown once; only their SHA-256 is stored.
 */
final class RecoveryCodes
{
    public const COUNT = 10;
    /** No 0/O, 1/I/L, so codes survive being written down. */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** @return list<string> codes formatted "xxxxx-xxxxx" */
    public static function generate(int $count = self::COUNT): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < 10; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[substr($raw, 0, 5) . '-' . substr($raw, 5)] = true;
        }

        return array_keys($codes);
    }

    public static function looksLike(string $input): bool
    {
        return preg_match('/^[a-z0-9]{10}$/', self::normalize($input)) === 1;
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    private static function normalize(string $code): string
    {
        return strtolower(preg_replace('/[\s-]+/', '', $code) ?? '');
    }
}
