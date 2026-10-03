<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Admin\SessionStore;
use Paxofi\CorporateWebsite\Application\Admin\StoredSession;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Staff sessions in `sessions`. Every UPDATE sets expires_at explicitly so a
 * server with explicit_defaults_for_timestamp=OFF can never auto-refresh it.
 */
final class PdoSessionStore implements SessionStore
{
    use GuardedQueries;

    private const FORMAT = 'Y-m-d H:i:s';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function create(string $tokenHash, string $userId, DateTimeImmutable $now, DateTimeImmutable $expiresAt, RequestContext $context): void
    {
        $this->write(
            'INSERT INTO sessions (id, user_id, expires_at, last_seen_at, created_at, source_ip, user_agent)
             VALUES (:id, :user_id, :expires_at, :last_seen_at, :created_at, :ip, :ua)',
            [
                'id' => $tokenHash,
                'user_id' => $userId,
                'expires_at' => self::utc($expiresAt),
                'last_seen_at' => self::utc($now),
                'created_at' => self::utc($now),
                'ip' => $context->clientIp,
                'ua' => $context->userAgent === null ? null : mb_substr(mb_scrub($context->userAgent, 'UTF-8'), 0, 500),
            ],
        );
    }

    public function find(string $tokenHash): ?StoredSession
    {
        $rows = $this->select('SELECT user_id, expires_at, last_seen_at, created_at, revoked_at FROM sessions WHERE id = :id', ['id' => $tokenHash]);
        if ($rows === []) {
            return null;
        }
        $row = $rows[0];
        $utc = new DateTimeZone('UTC');

        return new StoredSession(
            userId: (string) $row['user_id'],
            expiresAt: new DateTimeImmutable((string) $row['expires_at'], $utc),
            lastSeenAt: new DateTimeImmutable((string) ($row['last_seen_at'] ?? $row['created_at']), $utc),
            revoked: $row['revoked_at'] !== null,
        );
    }

    public function touch(string $tokenHash, DateTimeImmutable $now): void
    {
        $this->write('UPDATE sessions SET last_seen_at = :now, expires_at = expires_at WHERE id = :id', ['id' => $tokenHash, 'now' => self::utc($now)]);
    }

    public function revoke(string $tokenHash): void
    {
        $this->write('UPDATE sessions SET revoked_at = CURRENT_TIMESTAMP, expires_at = expires_at WHERE id = :id AND revoked_at IS NULL', ['id' => $tokenHash]);
    }

    public function revokeAllForUser(string $userId, ?string $exceptTokenHash = null): void
    {
        $this->write(
            'UPDATE sessions SET revoked_at = CURRENT_TIMESTAMP, expires_at = expires_at
             WHERE user_id = :user_id AND revoked_at IS NULL AND id <> :except',
            ['user_id' => $userId, 'except' => $exceptTokenHash ?? ''],
        );
    }

    private static function utc(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
