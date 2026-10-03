<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\LoginAttempts;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

final class PdoLoginAttempts implements LoginAttempts
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function record(string $email, ?string $clientIp, bool $succeeded): void
    {
        $this->write(
            'INSERT INTO login_attempts (id, email, source_ip, succeeded) VALUES (:id, :email, :ip, :ok)',
            ['id' => Uuid::v4(), 'email' => mb_substr($email, 0, 255), 'ip' => $clientIp, 'ok' => $succeeded ? 1 : 0],
        );
    }

    public function recentFailures(string $email, ?string $clientIp, int $windowMinutes): array
    {
        $since = sprintf('created_at >= (CURRENT_TIMESTAMP - INTERVAL %d MINUTE)', $windowMinutes);
        $byEmail = (int) ($this->select("SELECT COUNT(*) AS total FROM login_attempts WHERE email = :email AND succeeded = 0 AND {$since}", ['email' => $email])[0]['total'] ?? 0);
        $byIp = $clientIp === null ? 0 : (int) ($this->select("SELECT COUNT(*) AS total FROM login_attempts WHERE source_ip = :ip AND succeeded = 0 AND {$since}", ['ip' => $clientIp])[0]['total'] ?? 0);

        return ['email' => $byEmail, 'ip' => $byIp];
    }
}
