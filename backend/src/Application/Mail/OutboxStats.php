<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use DateTimeImmutable;

/** Counts from email_outbox for the staff Email page (D-031). No recipients or contents. */
interface OutboxStats
{
    /** @return array{pending: int, failed: int, sent_last_day: int, oldest_pending_at: ?string, last_error: ?string, last_error_kind: ?string, last_sent_at: ?string} */
    public function summary(DateTimeImmutable $now): array;
}
