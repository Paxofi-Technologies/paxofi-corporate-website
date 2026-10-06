<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

/**
 * What a redirect may send and where (D-025). "From" is an address on the
 * corporate website that no longer has a page; "to" is another address on the
 * site or a full https:// link.
 */
final class RedirectRules
{
    public const MAX_REDIRECTS = 500;

    /** Pages built into the website: a redirect would hide them. */
    public const FIXED_PAGES = [
        '/', '/about', '/services', '/products', '/industries', '/insights', '/resources', '/careers',
        '/contact', '/privacy', '/terms', '/sitemap.xml', '/robots.txt', '/release.txt',
    ];
    /** Addresses the website itself needs. */
    public const RESERVED_PREFIXES = ['/admin', '/api', '/_next', '/.well-known'];

    /** Lower-case, no trailing slash; null when not a valid site path. */
    public static function normalizeFrom(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $path = strtolower(trim($value));
        if (preg_match('#^https?://[^/]+(/.*)?$#', $path, $match) === 1) {
            $path = $match[1] ?? '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        if ($path === '' || strlen($path) > 255 || preg_match('#^/[a-z0-9\-._~%/]*$#', $path) !== 1
            || str_contains($path, '//') || str_contains($path, '/../') || str_ends_with($path, '/..')) {
            return null;
        }

        return $path;
    }

    /** The site path a target points to (no query or fragment), or null for an external link. */
    public static function targetPath(string $to): ?string
    {
        if (!str_starts_with($to, '/')) {
            return null;
        }
        $path = strtolower((string) preg_replace('/[?#].*$/', '', $to));

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function validTarget(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $to = trim($value);
        if ($to === '' || strlen($to) > 500 || preg_match('/[\s<>"\'\\\\]/', $to) === 1) {
            return null;
        }
        if (str_starts_with($to, '/')) {
            return !str_starts_with($to, '//') && preg_match('#^/[A-Za-z0-9\-._~%/]*(\?[A-Za-z0-9\-._~%=&+]*)?(\#[A-Za-z0-9\-_]*)?$#', $to) === 1 ? $to : null;
        }
        if (preg_match('#^https://#i', $to) !== 1 || filter_var($to, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $to;
    }

    public static function isReserved(string $path): bool
    {
        if (in_array($path, self::FIXED_PAGES, true)) {
            return true;
        }
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
