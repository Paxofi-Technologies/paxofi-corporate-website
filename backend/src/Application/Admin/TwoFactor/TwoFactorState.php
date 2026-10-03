<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

final readonly class TwoFactorState
{
    public function __construct(
        public ?string $encryptedSecret,
        public ?string $encryptedPendingSecret,
        public ?int $lastUsedStep,
        public int $recoveryCodesLeft,
    ) {
    }

    public function enabled(): bool
    {
        return $this->encryptedSecret !== null;
    }
}
