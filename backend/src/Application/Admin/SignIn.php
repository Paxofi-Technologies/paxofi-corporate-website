<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use DateTimeImmutable;

/** Result of a successful sign-in: the raw token goes into the cookie, never into storage. */
final readonly class SignIn
{
    public function __construct(public string $token, public StaffUser $user, public DateTimeImmutable $expiresAt)
    {
    }
}
