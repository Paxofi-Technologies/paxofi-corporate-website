<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Admin\PasswordResets;

/** password_resets (migration 013, D-016). */
final class PdoPasswordResets implements PasswordResets
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function create(string $tokenHash, string $userId, ?string $clientIp, DateTimeImmutable $now, DateTimeImmutable $expiresAt): void
    {
        $this->write(
            'INSERT INTO password_resets (token_hash, user_id, request_ip, created_at, expires_at) VALUES (:hash, :user, :ip, :now, :expires)',
            ['hash' => $tokenHash, 'user' => $userId, 'ip' => $clientIp, 'now' => $now->format('Y-m-d H:i:s'), 'expires' => $expiresAt->format('Y-m-d H:i:s')],
        );
    }

    public function find(string $tokenHash): ?array
    {
        $row = $this->select('SELECT user_id, expires_at, used_at FROM password_resets WHERE token_hash = :hash', ['hash' => $tokenHash])[0] ?? null;

        return $row === null ? null : [
            'user_id' => (string) $row['user_id'],
            'expires_at' => new DateTimeImmutable((string) $row['expires_at'], new DateTimeZone('UTC')),
            'used' => $row['used_at'] !== null,
        ];
    }

    public function markUsed(string $tokenHash, DateTimeImmutable $now): bool
    {
        return $this->write('UPDATE password_resets SET used_at = :now WHERE token_hash = :hash AND used_at IS NULL', ['hash' => $tokenHash, 'now' => $now->format('Y-m-d H:i:s')]) === 1;
    }

    public function deleteUnusedForUser(string $userId): void
    {
        $this->write('DELETE FROM password_resets WHERE user_id = :user AND used_at IS NULL', ['user' => $userId]);
    }

    public function recentRequests(?string $userId, ?string $clientIp, DateTimeImmutable $now, int $minutes): array
    {
        $since = $now->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');
        $user = $userId === null ? 0 : (int) ($this->select('SELECT COUNT(*) AS n FROM password_resets WHERE user_id = :user AND created_at >= :since', ['user' => $userId, 'since' => $since])[0]['n'] ?? 0);
        $ip = $clientIp === null ? 0 : (int) ($this->select('SELECT COUNT(*) AS n FROM password_resets WHERE request_ip = :ip AND created_at >= :since', ['ip' => $clientIp, 'since' => $since])[0]['n'] ?? 0);

        return ['user' => $user, 'ip' => $ip];
    }
}
