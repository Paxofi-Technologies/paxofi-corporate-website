<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Retention;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * How long personal and operational data is kept (decision D-008, SRS
 * Appendix J). Enquiries are deleted after 24 months; the IP address and
 * user-agent recorded for abuse protection are cleared after 90 days; audit
 * events are deleted after 24 months.
 */
final class RetentionPolicy
{
    public function __construct(
        public readonly int $enquiryMonths = 24,
        public readonly int $networkMetadataDays = 90,
        public readonly int $auditEventMonths = 24,
    ) {
        if ($enquiryMonths < 1 || $networkMetadataDays < 1 || $auditEventMonths < 1) {
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
}
