<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Infrastructure\Security;

use InvalidArgumentException;
use Paxofi\CorporateWebsite\Infrastructure\Security\OpenSslSecretEncryption;
use PHPUnit\Framework\TestCase;

final class OpenSslSecretEncryptionTest extends TestCase
{
    private const KEY = 'test-key-0123456789-abcdefghijklmnopqrstuvwxyz';

    public function testRoundTripsAndNeverRepeatsCiphertext(): void
    {
        $box = new OpenSslSecretEncryption(self::KEY);
        $secret = random_bytes(20);

        $first = $box->encrypt($secret);
        self::assertStringStartsWith('v1.', $first);
        self::assertNotSame($first, $box->encrypt($secret), 'fresh nonce each time');
        self::assertSame($secret, $box->decrypt($first));
        self::assertLessThanOrEqual(255, strlen($first), 'fits users.totp_secret');
    }

    public function testWrongKeyOrTamperingGivesNull(): void
    {
        $value = (new OpenSslSecretEncryption(self::KEY))->encrypt('secret');

        self::assertNull((new OpenSslSecretEncryption(strrev(self::KEY)))->decrypt($value));
        $raw = base64_decode(substr($value, 3), true);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        self::assertNull((new OpenSslSecretEncryption(self::KEY))->decrypt('v1.' . base64_encode($raw)));
        self::assertNull((new OpenSslSecretEncryption(self::KEY))->decrypt('plain text'));
    }

    public function testRefusesAShortKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new OpenSslSecretEncryption('too-short');
    }
}
