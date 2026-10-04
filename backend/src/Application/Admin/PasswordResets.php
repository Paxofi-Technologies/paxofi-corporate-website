<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use DateTimeImmutable;

/** password_resets (migration 013): one-time reset links, stored as SHA-256 hashes (D-016). */
interface PasswordResets
{
    public function create(string $tokenHash, string $userId, ?string $clientIp, DateTimeImmutable $now, DateTimeImmutable $expiresAt): void;

    /** @return array{user_id: string, expires_at: DateTimeImmutable, used: bool}|null */
    public function find(string $tokenHash): ?array;

    /** Marks the link used; false if it was already used (two tabs, a replay). */
    public function markUsed(string $tokenHash, DateTimeImmutable $now): bool;

    public function deleteUnusedForUser(string $userId): void;

    /** @return array{user: int, ip: int} requests in the last $minutes */
    public function recentRequests(?string $userId, ?string $clientIp, DateTimeImmutable $now, int $minutes): array;
}
