<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

/**
 * The visitor's address when a request reaches us through Cloudflare's proxy
 * (D-024). Cloudflare connects from its own addresses and passes the visitor's
 * in the CF-Connecting-IP header, which it always overwrites. The header is
 * believed only when the connection itself comes from a Cloudflare range, so a
 * request sent straight to the server cannot pick its own address.
 *
 * Ranges: https://www.cloudflare.com/ips/ (checked 6 Oct 2026). They change
 * rarely; review them at each yearly security review (RB-23).
 */
final class CloudflareClientIp
{
    public const RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * @param array<string, mixed> $server
     */
    public static function resolve(array $server): ?string
    {
        $peer = self::validIp($server['REMOTE_ADDR'] ?? null);
        if ($peer === null || !self::isCloudflare($peer)) {
            return $peer;
        }

        return self::validIp($server['HTTP_CF_CONNECTING_IP'] ?? null) ?? $peer;
    }

    public static function isCloudflare(string $ip): bool
    {
        $address = inet_pton($ip);
        if ($address === false) {
            return false;
        }
        foreach (self::RANGES as $range) {
            [$network, $bits] = explode('/', $range);
            $base = inet_pton($network);
            if ($base === false || strlen($base) !== strlen($address)) {
                continue;
            }
            $bits = (int) $bits;
            $whole = intdiv($bits, 8);
            if (substr($address, 0, $whole) !== substr($base, 0, $whole)) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($address[$whole]) & $mask) === (ord($base[$whole]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    private static function validIp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
    }
}
