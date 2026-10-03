<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use DateTimeImmutable;

/**
 * Result of a successful sign-in step: the raw token goes into the cookie, never
 * into storage. With $secondFactorPending the session only allows entering the
 * authenticator code (D-010).
 */
final readonly class SignIn
{
    public function __construct(
        public string $token,
        public StaffUser $user,
        public DateTimeImmutable $expiresAt,
        public bool $secondFactorPending = false,
    ) {
    }
}
