<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

/** Encrypts TOTP secrets at rest. */
interface SecretEncryption
{
    public function encrypt(string $plaintext): string;

    /** Returns null when the value cannot be decrypted (wrong key or tampered). */
    public function decrypt(string $ciphertext): ?string;
}
