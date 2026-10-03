<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** The staff member behind the current request, and their session. */
final readonly class AuthenticatedStaff
{
    /**
     * @param bool $secondFactorPending password accepted, authenticator code not yet (D-010)
     * @param bool $enrollmentRequired the role requires two-factor sign-in and it is not set up yet
     */
    public function __construct(
        public StaffUser $user,
        public string $sessionHash,
        public bool $secondFactorPending = false,
        public bool $enrollmentRequired = false,
    ) {
    }
}
