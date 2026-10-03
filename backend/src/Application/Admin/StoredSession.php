<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use DateTimeImmutable;

final readonly class StoredSession
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $lastSeenAt,
        public bool $revoked,
    ) {
    }
}
