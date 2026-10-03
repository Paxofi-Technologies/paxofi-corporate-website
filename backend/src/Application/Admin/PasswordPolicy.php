<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Staff password rules (decision D-009). Returns a client-safe message, or null when acceptable. */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 256;

    public static function problem(string $password, string $email): ?string
    {
        $length = mb_strlen($password);
        if ($length < self::MIN_LENGTH) {
            return sprintf('Use at least %d characters.', self::MIN_LENGTH);
        }
        if ($length > self::MAX_LENGTH) {
            return sprintf('Use at most %d characters.', self::MAX_LENGTH);
        }
        $local = strtolower(strstr($email, '@', true) ?: $email);
        if (strlen($local) >= 3 && str_contains(strtolower($password), $local)) {
            return 'Do not include your email address in the password.';
        }
        if (count(array_unique(mb_str_split($password))) < 5) {
            return 'Use a less repetitive password.';
        }

        return null;
    }
}
