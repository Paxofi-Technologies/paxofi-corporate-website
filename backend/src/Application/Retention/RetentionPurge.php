<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Retention;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * Applies the retention policy. Idempotent: running it twice removes nothing
 * more. Each run that changes data writes one `data_retention.purged` audit
 * event, so the purge itself is traceable. CV files are deleted once the
 * rows that point to them are gone (after the commit), through $deleteFile.
 */
final class RetentionPurge
{
    /** @param (Closure(string): void)|null $deleteFile removes a stored CV by its reference */
    public function __construct(private readonly PDO $pdo, private readonly RetentionPolicy $policy, private readonly ?Closure $deleteFile = null)
    {
    }

    /** @return array{enquiry_metadata_cleared: int, enquiries_deleted: int, audit_events_deleted: int, login_attempts_deleted: int, sessions_deleted: int, analytics_rows_deleted: int, emails_deleted: int, password_resets_deleted: int, applications_deleted: int, application_metadata_cleared: int, cv_uploads_deleted: int} */
    public function run(?DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
        $files = [];

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
                // Outbox and reset links (D-016).
                'emails_deleted' => $this->execute("DELETE FROM email_outbox WHERE status = 'sent' AND created_at < :cutoff", $this->policy->sentEmailCutoff($now))
                    + $this->execute("DELETE FROM email_outbox WHERE status = 'failed' AND created_at < :cutoff", $this->policy->failedEmailCutoff($now)),
                'password_resets_deleted' => $this->execute('DELETE FROM password_resets WHERE expires_at < :cutoff', $this->policy->passwordResetCutoff($now)),
                // Applications 12 months after closing, or after their last stage change while open (D-019).
                'applications_deleted' => $this->deleteWithFiles(
                    'job_applications',
                    'cv_reference',
                    'COALESCE(closed_at, stage_changed_at, created_at) < :cutoff',
                    $this->policy->applicationCutoff($now),
                    $files,
                ),
                'application_metadata_cleared' => $this->execute(
                    'UPDATE job_applications SET source_ip = NULL, user_agent = NULL
                     WHERE created_at < :cutoff AND (source_ip IS NOT NULL OR user_agent IS NOT NULL)',
                    $this->policy->networkMetadataCutoff($now),
                ),
                // A claimed upload's file belongs to its application, so only unclaimed files are deleted here.
                'cv_uploads_deleted' => $this->deleteWithFiles('application_uploads', 'file_reference', 'claimed_at IS NULL AND created_at < :cutoff', $this->policy->cvUploadCutoff($now), $files)
                    + $this->execute('DELETE FROM application_uploads WHERE claimed_at IS NOT NULL AND claimed_at < :cutoff', $this->policy->cvUploadCutoff($now)),
            ];

            if (array_sum($result) > 0) {
                $this->pdo->prepare(
                    "INSERT INTO audit_events (id, actor_id, action, target_type, target_id, outcome, request_id)
                     VALUES (:id, NULL, 'data_retention.purged', NULL, NULL, 'success', NULL)",
                )->execute(['id' => Uuid::v4()]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
        if ($this->deleteFile !== null) {
            foreach ($files as $reference) {
                ($this->deleteFile)($reference);
            }
        }

        return $result;
    }

    /**
     * Deletes the matching rows and collects the files they reference.
     *
     * @param list<string> $files
     */
    private function deleteWithFiles(string $table, string $fileColumn, string $condition, DateTimeImmutable $cutoff, array &$files): int
    {
        $select = $this->pdo->prepare("SELECT {$fileColumn} FROM {$table} WHERE {$condition} AND {$fileColumn} IS NOT NULL FOR UPDATE");
        $select->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $reference) {
            $files[] = (string) $reference;
        }

        return $this->execute("DELETE FROM {$table} WHERE {$condition}", $cutoff);
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
