<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** Permission names (table `permissions`, seeded by migration 007). */
final class Permission
{
    public const ENQUIRIES_READ = 'enquiries.read';
    public const ENQUIRIES_UPDATE = 'enquiries.update';
    public const USERS_MANAGE = 'users.manage';
    public const AUDIT_READ = 'audit.read';
}
