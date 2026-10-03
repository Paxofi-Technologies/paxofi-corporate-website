<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Opaque 256-bit session tokens; only their SHA-256 hash is ever stored. */
final class SessionToken
{
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function looksValid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }
}
