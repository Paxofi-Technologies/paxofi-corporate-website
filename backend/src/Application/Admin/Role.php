<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Staff roles (table `roles`, seeded by migration 007; decision D-009). */
enum Role: string
{
    case Administrator = 'administrator';
    case BusinessDevelopment = 'business_development';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::BusinessDevelopment => 'Business Development',
        };
    }
}
