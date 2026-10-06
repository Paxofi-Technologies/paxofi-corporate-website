<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Freshness\ContentReviews;
use Paxofi\CorporateWebsite\Application\Freshness\RedirectRules;
use PHPUnit\Framework\TestCase;

final class RedirectRulesTest extends TestCase
{
    public function testFromAddressesAreNormalised(): void
    {
        self::assertSame('/insights/old', RedirectRules::normalizeFrom(' /Insights/Old/ '));
        self::assertSame('/insights/old', RedirectRules::normalizeFrom('https://corporate.paxofi.com/insights/old'));
        self::assertSame('/', RedirectRules::normalizeFrom('https://corporate.paxofi.com'));
        self::assertSame('/caf%c3%a9', RedirectRules::normalizeFrom('/caf%C3%A9'));
        foreach (['', 'insights', '/a b', '/a?x=1', '/a#top', '//evil.example/x', '/a/../b', '/a/..', '/<script>', str_repeat('/a', 130), 42, null] as $bad) {
            self::assertNull(RedirectRules::normalizeFrom($bad), var_export($bad, true));
        }
    }

    public function testTargetsAreSitePathsOrHttpsLinks(): void
    {
        foreach (['/', '/insights/new', '/insights?category=news', '/products#paxoficloud', 'https://paxofi.com/x?y=1', 'HTTPS://Example.com'] as $good) {
            self::assertSame($good, RedirectRules::validTarget($good), $good);
        }
        foreach (['', 'http://example.com', 'javascript:alert(1)', 'data:text/html,x', '//evil.example', '/a b', "/a\nb", '/a"onmouseover', 'https://', 'ftp://x', 'insights'] as $bad) {
            self::assertNull(RedirectRules::validTarget($bad), $bad);
        }
        self::assertSame('/insights', RedirectRules::targetPath('/Insights/?category=news#x'));
        self::assertNull(RedirectRules::targetPath('https://paxofi.com/insights'));
    }

    public function testBuiltInAndSystemAddressesAreReserved(): void
    {
        foreach (['/', '/about', '/resources', '/admin', '/admin/users', '/api/v1/health', '/_next/static/x.js', '/sitemap.xml'] as $path) {
            self::assertTrue(RedirectRules::isReserved($path), $path);
        }
        foreach (['/about-us', '/insights/old', '/industries/fintech', '/administration'] as $path) {
            self::assertFalse(RedirectRules::isReserved($path), $path);
        }
    }

    public function testReviewStatusFollowsTheDate(): void
    {
        $today = new DateTimeImmutable('2026-10-06', new DateTimeZone('Africa/Lagos'));
        self::assertSame('none', ContentReviews::status(null, $today));
        self::assertSame('overdue', ContentReviews::status('2026-10-05', $today));
        self::assertSame('due', ContentReviews::status('2026-10-06', $today));
        self::assertSame('due', ContentReviews::status('2026-11-05', $today));
        self::assertSame('ok', ContentReviews::status('2026-11-06', $today));
        self::assertSame(['overdue' => 1, 'due' => 0, 'none' => 2, 'ok' => 0], ContentReviews::counts([['status' => 'none'], ['status' => 'overdue'], ['status' => 'none']]));
    }
}
