<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

interface LoginAttempts
{
    public function record(string $email, ?string $clientIp, bool $succeeded): void;

    /** @return array{email: int, ip: int} failed attempts in the window, for this email and for this IP address */
    public function recentFailures(string $email, ?string $clientIp, int $windowMinutes): array;
}
