<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\RequestContext;

/** Staff sessions, keyed by the SHA-256 hash of the session token. */
interface SessionStore
{
    public function create(string $tokenHash, string $userId, DateTimeImmutable $now, DateTimeImmutable $expiresAt, RequestContext $context): void;

    public function find(string $tokenHash): ?StoredSession;

    public function touch(string $tokenHash, DateTimeImmutable $now): void;

    public function revoke(string $tokenHash): void;

    /** Revokes every active session of the user except $exceptTokenHash. */
    public function revokeAllForUser(string $userId, ?string $exceptTokenHash = null): void;
}
