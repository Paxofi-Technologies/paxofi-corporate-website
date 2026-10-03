<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Security;

use Paxofi\CorporateWebsite\Application\Admin\PasswordHashing;

/** Argon2id when PHP was built with it (the usual case), otherwise bcrypt. */
final class NativeStaffPasswordHasher implements PasswordHashing
{
    private readonly string|int $algorithm;

    /** @param array<string, int> $options override for tests (cheaper costs) */
    public function __construct(private readonly array $options = [])
    {
        $this->algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, $this->options);
    }
}
