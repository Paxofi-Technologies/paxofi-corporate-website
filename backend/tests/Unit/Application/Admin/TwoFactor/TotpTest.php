<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application\Admin\TwoFactor;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\Base32;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\RecoveryCodes;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private const RFC_SECRET = '12345678901234567890';

    /** @return iterable<array{int, string}> RFC 6238 appendix B, SHA-1, 8 digits */
    public static function rfcVectors(): iterable
    {
        yield [59, '94287082'];
        yield [1111111109, '07081804'];
        yield [1111111111, '14050471'];
        yield [1234567890, '89005924'];
        yield [2000000000, '69279037'];
        yield [20000000000, '65353130'];
    }

    #[DataProvider('rfcVectors')]
    public function testMatchesTheRfc6238TestVectors(int $time, string $expected): void
    {
        self::assertSame($expected, Totp::code(self::RFC_SECRET, intdiv($time, 30), 8));
        self::assertSame(substr($expected, 2), Totp::code(self::RFC_SECRET, intdiv($time, 30)));
    }

    public function testAcceptsTheAdjacentStepsOnlyAndRefusesReuse(): void
    {
        $now = new DateTimeImmutable('@1234567890');
        $step = Totp::step($now);

        self::assertSame($step, Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $step), $now));
        self::assertSame($step - 1, Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $step - 1), $now));
        self::assertSame($step + 1, Totp::verify(self::RFC_SECRET, ' ' . Totp::code(self::RFC_SECRET, $step + 1), $now));
        self::assertNull(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $step - 2), $now));
        self::assertNull(Totp::verify(self::RFC_SECRET, Totp::code(self::RFC_SECRET, $step), $now, lastUsedStep: $step), 'a used code is refused');
        self::assertNull(Totp::verify(self::RFC_SECRET, '12345', $now));
        self::assertNull(Totp::verify(self::RFC_SECRET, 'abcdef', $now));
    }

    public function testProvisioningUriIsWhatAuthenticatorAppsExpect(): void
    {
        $uri = Totp::provisioningUri(self::RFC_SECRET, 'ada@paxofi.com');

        self::assertSame('otpauth://totp/Paxofi:ada%40paxofi.com?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=Paxofi&algorithm=SHA1&digits=6&period=30', $uri);
    }

    public function testBase32MatchesRfc4648(): void
    {
        self::assertSame('', Base32::encode(''));
        self::assertSame('MY', Base32::encode('f'));
        self::assertSame('MZXW6YTBOI', Base32::encode('foobar'));
        self::assertSame(32, strlen(Base32::encode(Totp::generateSecret())));
    }

    public function testRecoveryCodesAreUniqueReadableAndHashedWithoutFormatting(): void
    {
        $codes = RecoveryCodes::generate();

        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{5}-[a-hjkmnp-z2-9]{5}$/', $code);
            self::assertTrue(RecoveryCodes::looksLike($code));
        }
        self::assertSame(RecoveryCodes::hash('abcde-fghjk'), RecoveryCodes::hash(' ABCDE FGHJK '));
        self::assertFalse(RecoveryCodes::looksLike('123456'), 'an authenticator code is not a recovery code');
    }
}
