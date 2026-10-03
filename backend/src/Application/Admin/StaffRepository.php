<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

interface StaffRepository
{
    public function countAll(): int;

    public function countActiveAdministrators(): int;

    public function findCredentialsByEmail(string $email): ?StaffCredentials;

    public function findCredentialsById(string $id): ?StaffCredentials;

    public function findById(string $id): ?StaffUser;

    /** @return list<StaffUser> */
    public function all(): array;

    /** Creates an active account with one role and returns its id. */
    public function create(string $email, string $displayName, string $passwordHash, Role $role): string;

    public function updatePassword(string $id, string $passwordHash): void;

    public function recordLogin(string $id): void;

    public function update(string $id, ?string $displayName, ?string $status, ?Role $role): void;
}
