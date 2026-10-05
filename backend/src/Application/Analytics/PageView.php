<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Analytics;

/**
 * One page view, reduced to what the analytics keep (decision D-014): which
 * public page, which other site sent the visitor (first page of a visit only),
 * and the screen class. No URL query, no full referrer, no personal data.
 */
final readonly class PageView
{
    /** The public pages; anything else is counted as "(other)" so the tables stay small. */
    public const PAGES = ['/', '/about', '/services', '/products', '/careers', '/contact', '/privacy', '/terms', '/insights', '/industries'];
    public const OTHER_PAGE = '(other)';
    public const DIRECT = '(direct)';

    public function __construct(
        public string $path,
        /** Domain of the site the visit came from, '(direct)', or null when not the first page of a visit. */
        public ?string $source,
        public string $device,
    ) {
    }

    /**
     * @param array<string, mixed> $input {path, referrer?, entry?, width?}
     * @param list<string> $ownHosts this website's host names (a visit from them is not a source)
     */
    public static function fromInput(array $input, array $ownHosts): ?self
    {
        $path = is_string($input['path'] ?? null) ? $input['path'] : '';
        $path = strtolower(rtrim((string) parse_url($path, PHP_URL_PATH), '/')) ?: '/';
        if (str_starts_with($path, '/admin')) {
            return null;
        }
        // Each News & Insights article (D-021) is counted on its own address.
        $path = in_array($path, self::PAGES, true) || preg_match('~^/(insights/[a-z0-9-]{1,170}|industries/[a-z0-9-]{1,180})$~', $path) === 1 ? $path : self::OTHER_PAGE;

        $source = null;
        if (($input['entry'] ?? false) === true) {
            $source = self::source(is_string($input['referrer'] ?? null) ? $input['referrer'] : '', $ownHosts);
        }

        $width = $input['width'] ?? null;
        $device = match (true) {
            !is_int($width) || $width <= 0 || $width > 10000 => 'unknown',
            $width < 640 => 'phone',
            $width < 1024 => 'tablet',
            default => 'desktop',
        };

        return new self($path, $source, $device);
    }

    /** Referrer domain without "www.", '(direct)' when there is none; null when it is this website itself. */
    private static function source(string $referrer, array $ownHosts): ?string
    {
        $host = strtolower((string) parse_url(trim($referrer), PHP_URL_HOST));
        if ($host === '') {
            return self::DIRECT;
        }
        if (in_array($host, $ownHosts, true)) {
            return null;
        }
        $host = (string) preg_replace('/^www\./', '', $host);

        return preg_match('/^[a-z0-9.-]{1,100}$/', $host) === 1 ? $host : self::DIRECT;
    }
}
