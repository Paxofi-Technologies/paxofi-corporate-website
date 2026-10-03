<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

use DateTimeImmutable;
use Paxofi\Core\Contracts\HttpRequest;

/**
 * The staff session cookie (decision D-009): HttpOnly, SameSite=Strict and,
 * in production, Secure with the __Host- prefix (host-only, Path=/).
 */
final class SessionCookie
{
    private const NAME_SECURE = '__Host-paxofi_admin';
    private const NAME_PLAIN = 'paxofi_admin';

    public static function read(HttpRequest $request, bool $secure): ?string
    {
        $header = $request->header('cookie');
        if ($header === null) {
            return null;
        }
        $name = $secure ? self::NAME_SECURE : self::NAME_PLAIN;
        foreach (explode(';', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            if ($key === $name && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    public static function issue(string $token, DateTimeImmutable $expiresAt, DateTimeImmutable $now, bool $secure): string
    {
        $maxAge = max(0, $expiresAt->getTimestamp() - $now->getTimestamp());

        return self::build($token, $maxAge, $secure);
    }

    public static function clear(bool $secure): string
    {
        return self::build('', 0, $secure);
    }

    private static function build(string $value, int $maxAge, bool $secure): string
    {
        $cookie = sprintf('%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=Strict', $secure ? self::NAME_SECURE : self::NAME_PLAIN, $value, $maxAge);

        return $secure ? $cookie . '; Secure' : $cookie;
    }
}
