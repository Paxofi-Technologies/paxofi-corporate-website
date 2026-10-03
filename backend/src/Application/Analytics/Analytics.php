<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Analytics;

use Closure;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Cookieless visitor analytics (decision D-014).
 *
 * - Nothing is stored on the visitor's device and no personal data is kept:
 *   a visit is counted as a SHA-256 of (today's random salt, IP address,
 *   browser), and the salt and hashes are deleted when the day ends.
 * - Visitors who ask not to be tracked (Do Not Track, Global Privacy Control)
 *   and known bots are not counted.
 * - Staff see daily totals only.
 */
final class Analytics
{
    private const BOTS = '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|facebookexternalhit|curl|wget|python|httpclient|java\/|go-http|monitor|uptime/i';
    public const RANGES = [7, 30, 90];

    /**
     * @param list<string> $ownHosts
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private readonly AnalyticsStore $store,
        private readonly array $ownHosts,
        private readonly Closure $clock,
    ) {
    }

    /**
     * Counts a page view; returns false when it was not counted (bot, opt-out, admin page).
     *
     * @param array<string, mixed> $input
     */
    public function record(array $input, ?string $clientIp, ?string $userAgent, bool $optedOut): bool
    {
        $userAgent = trim((string) $userAgent);
        if ($optedOut || $userAgent === '' || preg_match(self::BOTS, $userAgent) === 1) {
            return false;
        }
        $view = PageView::fromInput($input, $this->ownHosts);
        if ($view === null) {
            return false;
        }
        $today = ($this->clock)()->setTime(0, 0);
        $hash = hash('sha256', $this->store->salt($today) . '|' . ($clientIp ?? '') . '|' . $userAgent);
        $this->store->record($today, $view, $hash);

        return true;
    }

    /** @return array<string, mixed> */
    public function report(int $days): array
    {
        $days = in_array($days, self::RANGES, true) ? $days : 30;
        $to = ($this->clock)()->setTime(0, 0);
        $from = $to->sub(new DateInterval('P' . ($days - 1) . 'D'));
        $data = $this->store->report($from, $to);

        $byDay = [];
        foreach ($data['daily'] as $row) {
            $byDay[$row['day']] = $row;
        }
        $daily = [];
        foreach (new DatePeriod($from, new DateInterval('P1D'), $to->modify('+1 day')) as $date) {
            $key = $date->format('Y-m-d');
            $daily[] = [
                'day' => $key,
                'views' => $byDay[$key]['views'] ?? 0,
                'visitors' => $byDay[$key]['visitors'] ?? 0,
                'enquiries' => $data['enquiries'][$key] ?? 0,
            ];
        }

        return [
            'days' => $days,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'totals' => [
                'views' => array_sum(array_column($daily, 'views')),
                'visitors' => array_sum(array_column($daily, 'visitors')),
                'enquiries' => array_sum(array_column($daily, 'enquiries')),
            ],
            'daily' => $daily,
            'pages' => $data['pages'],
            'sources' => array_slice($data['sources'], 0, 10),
            'devices' => $data['devices'],
        ];
    }
}
