<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use DateTimeImmutable;

/** Limits repeated alerts about the same problem (D-016). Works without the database. */
interface AlertThrottle
{
    /** True (and remembers the time) when no alert with this fingerprint went out within $seconds. */
    public function allow(string $fingerprint, DateTimeImmutable $now, int $seconds): bool;
}
