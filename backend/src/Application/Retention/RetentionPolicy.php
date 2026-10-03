<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Retention;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * How long personal and operational data is kept (decision D-008, SRS
 * Appendix J). Enquiries are deleted after 24 months; the IP address and
 * user-agent recorded for abuse protection are cleared after 90 days; audit
 * events are deleted after 24 months. Staff sign-in attempts are deleted after
 * 90 days and ended staff sessions after 30 days (D-009). Visitor analytics
 * keep daily totals for 25 months; the day's visitor hashes go when the day
 * ends (D-014).
 */
final class RetentionPolicy
{
    public function __construct(
        public readonly int $enquiryMonths = 24,
        public readonly int $networkMetadataDays = 90,
        public readonly int $auditEventMonths = 24,
        public readonly int $loginAttemptDays = 90,
        public readonly int $endedSessionDays = 30,
        public readonly int $analyticsMonths = 25,
    ) {
        if ($enquiryMonths < 1 || $networkMetadataDays < 1 || $auditEventMonths < 1 || $loginAttemptDays < 1 || $endedSessionDays < 1 || $analyticsMonths < 1) {
            throw new InvalidArgumentException('Retention periods must be positive.');
        }
    }

    public function enquiryCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->enquiryMonths} months");
    }

    public function networkMetadataCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->networkMetadataDays} days");
    }

    public function auditEventCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->auditEventMonths} months");
    }

    public function loginAttemptCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->loginAttemptDays} days");
    }

    public function analyticsCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->analyticsMonths} months");
    }

    /** Visitor hashes and salts from before today (UTC). */
    public function analyticsVisitorCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->setTime(0, 0);
    }

    public function endedSessionCutoff(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify("-{$this->endedSessionDays} days");
    }
}
