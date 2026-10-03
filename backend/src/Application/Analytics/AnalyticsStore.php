<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Analytics;

use DateTimeImmutable;

interface AnalyticsStore
{
    /** The random salt for this day; creating it also deletes earlier days' salts and visitor hashes. */
    public function salt(DateTimeImmutable $day): string;

    /** Adds one view (and one visitor when the hash is new for that page today). */
    public function record(DateTimeImmutable $day, PageView $view, string $visitorHash): void;

    /**
     * Daily totals between two days (inclusive).
     *
     * @return array{daily: list<array{day: string, views: int, visitors: int}>, pages: list<array{path: string, views: int, visitors: int}>, sources: list<array{source: string, views: int}>, devices: list<array{device: string, views: int}>, enquiries: array<string, int>}
     */
    public function report(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
