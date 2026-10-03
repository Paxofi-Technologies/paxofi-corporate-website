<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** The staff member behind the current request, and their session. */
final readonly class AuthenticatedStaff
{
    public function __construct(public StaffUser $user, public string $sessionHash)
    {
    }
}
