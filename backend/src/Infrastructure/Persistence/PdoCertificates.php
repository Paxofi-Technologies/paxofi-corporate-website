<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Careers\Certificates;

/** certificates (migration 023, D-029). */
final class PdoCertificates implements Certificates
{
    use GuardedQueries;

    private const COLUMNS = 'id, code, application_id, holder_name, role_title, engagement_type, cohort, started_on, completed_on, status, revoked_at, revoked_reason, issued_by, issued_at';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function add(array $certificate): void
    {
        $this->write(
            'INSERT INTO certificates (id, code, application_id, holder_name, role_title, engagement_type, cohort, started_on, completed_on, issued_by)
             VALUES (:id, :code, :application_id, :holder_name, :role_title, :engagement_type, :cohort, :started_on, :completed_on, :issued_by)',
            $certificate,
        );
    }

    public function find(string $id): ?array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM certificates WHERE id = :id', ['id' => $id])[0] ?? null;
    }

    public function findByCode(string $code): ?array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM certificates WHERE code = :code', ['code' => $code])[0] ?? null;
    }

    public function forApplication(string $applicationId): array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM certificates WHERE application_id = :id ORDER BY issued_at DESC, id', ['id' => $applicationId]);
    }

    public function revoke(string $id, string $reason, DateTimeImmutable $now): int
    {
        return $this->write(
            "UPDATE certificates SET status = 'revoked', revoked_at = :now, revoked_reason = :reason WHERE id = :id AND status = 'valid'",
            ['id' => $id, 'reason' => $reason, 'now' => $now->format('Y-m-d H:i:s')],
        );
    }

    public function delete(string $id): int
    {
        return $this->write('DELETE FROM certificates WHERE id = :id', ['id' => $id]);
    }
}
