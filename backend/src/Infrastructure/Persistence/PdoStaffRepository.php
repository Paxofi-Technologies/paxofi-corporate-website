<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\Role;
use Paxofi\CorporateWebsite\Application\Admin\StaffCredentials;
use Paxofi\CorporateWebsite\Application\Admin\StaffRepository;
use Paxofi\CorporateWebsite\Application\Admin\StaffUser;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

final class PdoStaffRepository implements StaffRepository
{
    use GuardedQueries;

    private const SELECT = 'SELECT u.id, u.email, u.display_name, u.status, u.password_hash, u.last_login_at, u.totp_enabled_at,
            (SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id ORDER BY r.name LIMIT 1) AS role_name
        FROM users u';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function countAll(): int
    {
        return (int) ($this->select('SELECT COUNT(*) AS total FROM users')[0]['total'] ?? 0);
    }

    public function countActiveAdministrators(): int
    {
        return (int) ($this->select(
            "SELECT COUNT(*) AS total FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
             WHERE u.status = 'active' AND r.name = :role",
            ['role' => Role::Administrator->value],
        )[0]['total'] ?? 0);
    }

    public function findCredentialsByEmail(string $email): ?StaffCredentials
    {
        $rows = $this->select(self::SELECT . ' WHERE u.email = :email', ['email' => strtolower($email)]);

        return $rows === [] ? null : new StaffCredentials($this->hydrate($rows[0]), $rows[0]['password_hash'] ?? null);
    }

    public function findCredentialsById(string $id): ?StaffCredentials
    {
        $rows = $this->select(self::SELECT . ' WHERE u.id = :id', ['id' => $id]);

        return $rows === [] ? null : new StaffCredentials($this->hydrate($rows[0]), $rows[0]['password_hash'] ?? null);
    }

    public function findById(string $id): ?StaffUser
    {
        return $this->findCredentialsById($id)?->user;
    }

    public function all(): array
    {
        return array_map($this->hydrate(...), $this->select(self::SELECT . ' ORDER BY u.status, u.display_name, u.email'));
    }

    public function create(string $email, string $displayName, string $passwordHash, Role $role): string
    {
        $id = Uuid::v4();
        $this->write(
            "INSERT INTO users (id, email, display_name, password_hash, password_changed_at, status)
             VALUES (:id, :email, :name, :hash, CURRENT_TIMESTAMP, 'active')",
            ['id' => $id, 'email' => strtolower($email), 'name' => $displayName, 'hash' => $passwordHash],
        );
        $this->write(
            'INSERT INTO user_roles (user_id, role_id) SELECT :id, r.id FROM roles r WHERE r.name = :role',
            ['id' => $id, 'role' => $role->value],
        );

        return $id;
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $this->write('UPDATE users SET password_hash = :hash, password_changed_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $id, 'hash' => $passwordHash]);
    }

    public function recordLogin(string $id): void
    {
        $this->write('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id', ['id' => $id]);
    }

    public function update(string $id, ?string $displayName, ?string $status, ?Role $role): void
    {
        if ($displayName !== null) {
            $this->write('UPDATE users SET display_name = :name WHERE id = :id', ['id' => $id, 'name' => $displayName]);
        }
        if ($status !== null) {
            $this->write('UPDATE users SET status = :status WHERE id = :id', ['id' => $id, 'status' => $status]);
        }
        if ($role !== null) {
            $this->write('DELETE FROM user_roles WHERE user_id = :id', ['id' => $id]);
            $this->write('INSERT INTO user_roles (user_id, role_id) SELECT :id, r.id FROM roles r WHERE r.name = :role', ['id' => $id, 'role' => $role->value]);
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): StaffUser
    {
        $role = is_string($row['role_name'] ?? null) ? Role::tryFrom($row['role_name']) : null;
        $permissions = $role === null ? [] : array_column($this->select(
            'SELECT p.name FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id JOIN roles r ON r.id = rp.role_id
             WHERE r.name = :role ORDER BY p.name',
            ['role' => $role->value],
        ), 'name');

        return new StaffUser(
            id: (string) $row['id'],
            email: (string) $row['email'],
            displayName: (string) ($row['display_name'] ?? $row['email']),
            status: (string) $row['status'],
            role: $role,
            permissions: array_values(array_map('strval', $permissions)),
            lastLoginAt: isset($row['last_login_at']) ? (string) $row['last_login_at'] : null,
            twoFactorEnabled: ($row['totp_enabled_at'] ?? null) !== null,
        );
    }
}
