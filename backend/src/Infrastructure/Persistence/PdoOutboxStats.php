<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Mail\OutboxStats;

/** email_outbox counts (D-031). */
final class PdoOutboxStats implements OutboxStats
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function summary(DateTimeImmutable $now): array
    {
        $counts = $this->select(
            "SELECT
                SUM(status = 'pending') AS pending,
                SUM(status = 'failed') AS failed,
                SUM(status = 'sent' AND sent_at >= :since) AS sent_last_day,
                MIN(CASE WHEN status = 'pending' THEN created_at END) AS oldest_pending_at,
                MAX(sent_at) AS last_sent_at
             FROM email_outbox",
            ['since' => $now->modify('-1 day')->format('Y-m-d H:i:s')],
        )[0] ?? [];
        $error = $this->select("SELECT last_error, kind FROM email_outbox WHERE last_error IS NOT NULL ORDER BY next_attempt_at DESC, created_at DESC LIMIT 1")[0] ?? null;

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'sent_last_day' => (int) ($counts['sent_last_day'] ?? 0),
            'oldest_pending_at' => $counts['oldest_pending_at'] === null ? null : substr((string) $counts['oldest_pending_at'], 0, 19),
            'last_error' => $error === null ? null : (string) $error['last_error'],
            'last_error_kind' => $error === null ? null : (string) $error['kind'],
            'last_sent_at' => ($counts['last_sent_at'] ?? null) === null ? null : substr((string) $counts['last_sent_at'], 0, 19),
        ];
    }
}
