<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

/** A staff account as seen by the application (never carries the password hash). */
final readonly class StaffUser
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    /** @param list<string> $permissions */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $status,
        public ?Role $role,
        public array $permissions,
        public ?string $lastLoginAt = null,
        public bool $twoFactorEnabled = false,
    ) {
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'status' => $this->status,
            'role' => $this->role?->value,
            'role_label' => $this->role?->label(),
            'permissions' => $this->permissions,
            'last_login_at' => $this->lastLoginAt,
            'two_factor_enabled' => $this->twoFactorEnabled,
        ];
    }
}
