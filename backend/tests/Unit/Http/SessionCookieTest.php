<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Http;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Http\SessionCookie;
use Paxofi\CorporateWebsite\Tests\Support\HttpRequests;
use PHPUnit\Framework\TestCase;

final class SessionCookieTest extends TestCase
{
    use HttpRequests;

    public function testProductionCookieIsHostOnlySecureHttpOnlyAndStrict(): void
    {
        $now = new DateTimeImmutable('2026-10-03 09:00:00');
        $cookie = SessionCookie::issue('tok', $now->modify('+8 hours'), $now, true);

        self::assertSame('__Host-paxofi_admin=tok; Path=/; Max-Age=28800; HttpOnly; SameSite=Strict; Secure', $cookie);
        self::assertStringContainsString('Max-Age=0', SessionCookie::clear(true));
    }

    public function testReadsOnlyTheMatchingCookieName(): void
    {
        $request = self::request('GET', '/', ['cookie' => 'other=1; paxofi_admin=plain; __Host-paxofi_admin=secure']);

        self::assertSame('secure', SessionCookie::read($request, true));
        self::assertSame('plain', SessionCookie::read($request, false));
        self::assertNull(SessionCookie::read(self::request('GET', '/'), true));
    }
}
