<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application\Retention;

use DateTimeImmutable;
use InvalidArgumentException;
use Paxofi\CorporateWebsite\Application\Retention\RetentionPolicy;
use PHPUnit\Framework\TestCase;

final class RetentionPolicyTest extends TestCase
{
    public function testDefaultPeriodsMatchDecisionD008(): void
    {
        $policy = new RetentionPolicy();
        $now = new DateTimeImmutable('2026-10-02 12:00:00');

        self::assertSame('2024-10-02 12:00:00', $policy->enquiryCutoff($now)->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-04 12:00:00', $policy->networkMetadataCutoff($now)->format('Y-m-d H:i:s'));
        self::assertSame('2024-10-02 12:00:00', $policy->auditEventCutoff($now)->format('Y-m-d H:i:s'));
    }

    public function testRejectsNonPositivePeriods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RetentionPolicy(enquiryMonths: 0);
    }
}
