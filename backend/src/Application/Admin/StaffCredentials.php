<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

final readonly class StaffCredentials
{
    public function __construct(public StaffUser $user, public ?string $passwordHash)
    {
    }
}
