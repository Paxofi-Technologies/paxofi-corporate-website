<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Retention;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * Applies the retention policy. Idempotent: running it twice removes nothing
 * more. Each run that changes data writes one `data_retention.purged` audit
 * event, so the purge itself is traceable.
 */
final class RetentionPurge
{
    public function __construct(private readonly PDO $pdo, private readonly RetentionPolicy $policy)
    {
    }

    /** @return array{enquiry_metadata_cleared: int, enquiries_deleted: int, audit_events_deleted: int, login_attempts_deleted: int, sessions_deleted: int, analytics_rows_deleted: int} */
    public function run(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));

        $this->pdo->beginTransaction();
        try {
            $result = [
                'enquiry_metadata_cleared' => $this->execute(
                    'UPDATE enquiries SET source_ip = NULL, user_agent = NULL
                     WHERE created_at < :cutoff AND (source_ip IS NOT NULL OR user_agent IS NOT NULL)',
                    $this->policy->networkMetadataCutoff($now),
                ),
                'enquiries_deleted' => $this->execute(
                    'DELETE FROM enquiries WHERE created_at < :cutoff',
                    $this->policy->enquiryCutoff($now),
                ),
                'audit_events_deleted' => $this->execute(
                    'DELETE FROM audit_events WHERE created_at < :cutoff',
                    $this->policy->auditEventCutoff($now),
                ),
                'login_attempts_deleted' => $this->execute(
                    'DELETE FROM login_attempts WHERE created_at < :cutoff',
                    $this->policy->loginAttemptCutoff($now),
                ),
                // Ended = expired or revoked; both timestamps are older than the cutoff.
                'sessions_deleted' => $this->execute(
                    'DELETE FROM sessions WHERE COALESCE(revoked_at, expires_at) < :cutoff AND expires_at < :cutoff2',
                    $this->policy->endedSessionCutoff($now),
                ),
                // Daily totals after 25 months; visitor hashes and salts as soon as their day is over (D-014).
                'analytics_rows_deleted' => $this->execute('DELETE FROM analytics_daily WHERE day < :cutoff', $this->policy->analyticsCutoff($now))
                    + $this->execute('DELETE FROM analytics_sources WHERE day < :cutoff', $this->policy->analyticsCutoff($now))
                    + $this->execute('DELETE FROM analytics_devices WHERE day < :cutoff', $this->policy->analyticsCutoff($now))
                    + $this->execute('DELETE FROM analytics_visitors WHERE day < :cutoff', $this->policy->analyticsVisitorCutoff($now))
                    + $this->execute('DELETE FROM analytics_salts WHERE day < :cutoff', $this->policy->analyticsVisitorCutoff($now)),
            ];

            if (array_sum($result) > 0) {
                $this->pdo->prepare(
                    "INSERT INTO audit_events (id, actor_id, action, target_type, target_id, outcome, request_id)
                     VALUES (:id, NULL, 'data_retention.purged', NULL, NULL, 'success', NULL)",
                )->execute(['id' => Uuid::v4()]);
            }

            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function execute(string $sql, DateTimeImmutable $cutoff): int
    {
        $statement = $this->pdo->prepare($sql);
        $parameters = ['cutoff' => $cutoff->format('Y-m-d H:i:s')];
        if (str_contains($sql, ':cutoff2')) {
            $parameters['cutoff2'] = $parameters['cutoff'];
        }
        $statement->execute($parameters);

        return $statement->rowCount();
    }
}
