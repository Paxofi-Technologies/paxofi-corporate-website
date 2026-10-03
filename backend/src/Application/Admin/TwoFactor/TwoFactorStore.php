<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

interface TwoFactorStore
{
    public function find(string $userId): TwoFactorState;

    public function setPending(string $userId, string $encryptedSecret): void;

    /** Turns the pending secret into the active one and stores new recovery code hashes. */
    public function enable(string $userId, string $encryptedSecret, int $usedStep, array $recoveryCodeHashes): void;

    /** Records a used step; false when that step (or a later one) was already used. */
    public function useStep(string $userId, int $step): bool;

    /** Marks an unused recovery code as used; false when there is none. */
    public function useRecoveryCode(string $userId, string $codeHash): bool;

    /** @param list<string> $codeHashes */
    public function replaceRecoveryCodes(string $userId, array $codeHashes): void;

    /** Removes the secret, the pending secret and all recovery codes. */
    public function disable(string $userId): void;
}
