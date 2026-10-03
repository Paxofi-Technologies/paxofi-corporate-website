<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Security;

use InvalidArgumentException;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\SecretEncryption;

/**
 * AES-256-GCM with a key derived (HKDF-SHA256) from MFA_ENCRYPTION_KEY.
 * Output: "v1." + base64(nonce[12] . tag[16] . ciphertext). GCM authenticates
 * the data, so a wrong key or a modified value decrypts to null.
 */
final class OpenSslSecretEncryption implements SecretEncryption
{
    public const MIN_KEY_LENGTH = 32;
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1.';

    private readonly string $key;

    public function __construct(string $configuredKey)
    {
        if (strlen($configuredKey) < self::MIN_KEY_LENGTH) {
            throw new InvalidArgumentException('MFA_ENCRYPTION_KEY must be at least 32 characters.');
        }
        $this->key = hash_hkdf('sha256', $configuredKey, 32, 'paxofi-corporate-website/totp-secret/v1');
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return self::PREFIX . base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $ciphertext): ?string
    {
        if (!str_starts_with($ciphertext, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($ciphertext, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plaintext = openssl_decrypt(substr($raw, 28), self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plaintext === false ? null : $plaintext;
    }
}
