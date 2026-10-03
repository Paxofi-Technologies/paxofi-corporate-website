<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application\Admin;

use Paxofi\CorporateWebsite\Application\Admin\PasswordPolicy;
use Paxofi\CorporateWebsite\Application\Admin\SessionToken;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testAcceptsALongPassPhrase(): void
    {
        self::assertNull(PasswordPolicy::problem('Correct horse battery 42', 'founder@paxofi.com'));
    }

    public function testRejectsShortRepetitiveOrEmailBasedPasswords(): void
    {
        self::assertNotNull(PasswordPolicy::problem('short', 'a@paxofi.com'));
        self::assertNotNull(PasswordPolicy::problem('aaaaaaaaaaaaaaaa', 'a@paxofi.com'));
        self::assertNotNull(PasswordPolicy::problem('my-founder-password-1', 'founder@paxofi.com'));
        self::assertNotNull(PasswordPolicy::problem(str_repeat('ab1!', 70), 'a@paxofi.com'));
    }

    public function testSessionTokensAreRandomAndOnlyTheirHashIsUsedForLookup(): void
    {
        $a = SessionToken::generate();
        $b = SessionToken::generate();

        self::assertNotSame($a, $b);
        self::assertTrue(SessionToken::looksValid($a));
        self::assertFalse(SessionToken::looksValid('not-a-token'));
        self::assertSame(64, strlen(SessionToken::hash($a)));
    }
}
