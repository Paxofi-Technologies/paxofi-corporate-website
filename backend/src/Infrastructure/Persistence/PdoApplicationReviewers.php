<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Careers\ApplicationReviewers;

/** application_reviewers (migration 023, D-029). */
final class PdoApplicationReviewers implements ApplicationReviewers
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function forApplication(string $applicationId): array
    {
        return array_map(static fn (array $row): array => [
            'user_id' => (string) $row['user_id'],
            'name' => (string) ($row['display_name'] ?? $row['email']),
            'status' => (string) $row['status'],
            'reason' => $row['recusal_reason'] === null ? null : (string) $row['recusal_reason'],
            'created_at' => substr((string) $row['created_at'], 0, 19),
        ], $this->select(
            'SELECT r.user_id, r.status, r.recusal_reason, r.created_at, u.display_name, u.email
             FROM application_reviewers r JOIN users u ON u.id = r.user_id
             WHERE r.application_id = :id ORDER BY r.created_at, r.id',
            ['id' => $applicationId],
        ));
    }

    public function find(string $applicationId, string $userId): ?array
    {
        $row = $this->select(
            'SELECT status FROM application_reviewers WHERE application_id = :application AND user_id = :user',
            ['application' => $applicationId, 'user' => $userId],
        )[0] ?? null;

        return $row === null ? null : ['status' => (string) $row['status']];
    }

    public function assign(string $id, string $applicationId, string $userId, ?string $assignedBy): void
    {
        $this->write(
            'INSERT INTO application_reviewers (id, application_id, user_id, status, assigned_by) VALUES (:id, :application, :user, \'assigned\', :by)',
            ['id' => $id, 'application' => $applicationId, 'user' => $userId, 'by' => $assignedBy],
        );
    }

    public function remove(string $applicationId, string $userId): int
    {
        return $this->write(
            'DELETE FROM application_reviewers WHERE application_id = :application AND user_id = :user AND status = \'assigned\'',
            ['application' => $applicationId, 'user' => $userId],
        );
    }

    public function recuse(string $id, string $applicationId, string $userId, string $reason): void
    {
        $this->write(
            'INSERT INTO application_reviewers (id, application_id, user_id, status, recusal_reason) VALUES (:id, :application, :user, \'recused\', :reason)
             ON DUPLICATE KEY UPDATE status = \'recused\', recusal_reason = VALUES(recusal_reason)',
            ['id' => $id, 'application' => $applicationId, 'user' => $userId, 'reason' => $reason],
        );
    }

    public function emailOf(string $userId): ?string
    {
        $row = $this->select('SELECT email FROM users WHERE id = :id', ['id' => $userId])[0] ?? null;

        return $row === null ? null : (string) $row['email'];
    }

    public function eligibleStaff(): array
    {
        return array_map(static fn (array $row): array => ['id' => (string) $row['id'], 'name' => (string) ($row['display_name'] ?? $row['email'])], $this->select(
            "SELECT DISTINCT u.id, u.display_name, u.email
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE u.status = 'active' AND p.name = 'recruitment.read'
             ORDER BY COALESCE(u.display_name, u.email), u.id",
        ));
    }
}
