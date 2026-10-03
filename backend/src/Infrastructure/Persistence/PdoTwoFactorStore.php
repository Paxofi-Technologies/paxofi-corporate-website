<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\TwoFactorState;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\TwoFactorStore;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/** Two-factor data in `users` (encrypted secrets, last used step) and `recovery_codes` (hashes). */
final class PdoTwoFactorStore implements TwoFactorStore
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function find(string $userId): TwoFactorState
    {
        $row = $this->select(
            'SELECT u.totp_secret, u.totp_pending_secret, u.totp_last_step,
                    (SELECT COUNT(*) FROM recovery_codes rc WHERE rc.user_id = u.id AND rc.used_at IS NULL) AS codes_left
             FROM users u WHERE u.id = :id',
            ['id' => $userId],
        )[0] ?? [];

        return new TwoFactorState(
            encryptedSecret: isset($row['totp_secret']) ? (string) $row['totp_secret'] : null,
            encryptedPendingSecret: isset($row['totp_pending_secret']) ? (string) $row['totp_pending_secret'] : null,
            lastUsedStep: isset($row['totp_last_step']) ? (int) $row['totp_last_step'] : null,
            recoveryCodesLeft: (int) ($row['codes_left'] ?? 0),
        );
    }

    public function setPending(string $userId, string $encryptedSecret): void
    {
        $this->write('UPDATE users SET totp_pending_secret = :secret WHERE id = :id', ['id' => $userId, 'secret' => $encryptedSecret]);
    }

    public function enable(string $userId, string $encryptedSecret, int $usedStep, array $recoveryCodeHashes): void
    {
        $this->write(
            'UPDATE users SET totp_secret = :secret, totp_pending_secret = NULL, totp_enabled_at = CURRENT_TIMESTAMP, totp_last_step = :step WHERE id = :id',
            ['id' => $userId, 'secret' => $encryptedSecret, 'step' => $usedStep],
        );
        $this->replaceRecoveryCodes($userId, $recoveryCodeHashes);
    }

    public function useStep(string $userId, int $step): bool
    {
        return $this->write(
            'UPDATE users SET totp_last_step = :step WHERE id = :id AND totp_secret IS NOT NULL AND (totp_last_step IS NULL OR totp_last_step < :previous)',
            ['id' => $userId, 'step' => $step, 'previous' => $step],
        ) > 0;
    }

    public function useRecoveryCode(string $userId, string $codeHash): bool
    {
        return $this->write(
            'UPDATE recovery_codes SET used_at = CURRENT_TIMESTAMP WHERE user_id = :id AND code_hash = :hash AND used_at IS NULL',
            ['id' => $userId, 'hash' => $codeHash],
        ) > 0;
    }

    public function replaceRecoveryCodes(string $userId, array $codeHashes): void
    {
        $this->write('DELETE FROM recovery_codes WHERE user_id = :id', ['id' => $userId]);
        foreach ($codeHashes as $hash) {
            $this->write(
                'INSERT INTO recovery_codes (id, user_id, code_hash) VALUES (:id, :user_id, :hash)',
                ['id' => Uuid::v4(), 'user_id' => $userId, 'hash' => $hash],
            );
        }
    }

    public function disable(string $userId): void
    {
        $this->write(
            'UPDATE users SET totp_secret = NULL, totp_pending_secret = NULL, totp_enabled_at = NULL, totp_last_step = NULL WHERE id = :id',
            ['id' => $userId],
        );
        $this->write('DELETE FROM recovery_codes WHERE user_id = :id', ['id' => $userId]);
    }
}
