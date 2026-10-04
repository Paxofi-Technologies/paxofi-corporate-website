<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use DateTimeImmutable;

/** email_outbox (migration 013): emails waiting to be sent, with retries (D-016). */
interface Outbox
{
    public function add(Email $email): string;

    /** @return list<array{id: string, attempts: int, email: Email}> due emails, oldest first */
    public function due(DateTimeImmutable $now, int $limit): array;

    public function markSent(string $id, DateTimeImmutable $now): void;

    /** Records a failed attempt; $nextAttempt null means give up (status "failed"). */
    public function markFailed(string $id, string $error, ?DateTimeImmutable $nextAttempt): void;

    /** Emails given up on since the time given. */
    public function failedSince(DateTimeImmutable $since): int;
}
