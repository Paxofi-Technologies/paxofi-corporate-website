<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Careers\TalentPoolStore;

/** talent_pool (migration 023, D-029). */
final class PdoTalentPool implements TalentPoolStore
{
    use GuardedQueries;

    private const COLUMNS = 'id, application_id, reference, full_name, email, location, role_title, engagement_type, portfolio_url, linkedin_url, note, consent_given_on, consent_method, consent_expires_on, added_by, created_at';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function add(array $entry): void
    {
        $this->write(
            'INSERT INTO talent_pool (id, application_id, reference, full_name, email, location, role_title, engagement_type, portfolio_url, linkedin_url, note, consent_given_on, consent_method, consent_expires_on, added_by)
             VALUES (:id, :application_id, :reference, :full_name, :email, :location, :role_title, :engagement_type, :portfolio_url, :linkedin_url, :note, :consent_given_on, :consent_method, :consent_expires_on, :added_by)',
            $entry,
        );
    }

    public function find(string $id): ?array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM talent_pool WHERE id = :id', ['id' => $id])[0] ?? null;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM talent_pool WHERE email = :email', ['email' => strtolower($email)])[0] ?? null;
    }

    public function forApplication(string $applicationId): ?array
    {
        return $this->select('SELECT ' . self::COLUMNS . ' FROM talent_pool WHERE application_id = :id', ['id' => $applicationId])[0] ?? null;
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        if (isset($filters['engagement'])) {
            $where[] = 'engagement_type = :engagement';
            $parameters['engagement'] = $filters['engagement'];
        }
        if (isset($filters['q'])) {
            $where[] = '(full_name LIKE :q1 OR email LIKE :q2 OR role_title LIKE :q3 OR reference LIKE :q4)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $parameters += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $condition = implode(' AND ', $where);
        $total = (int) ($this->select("SELECT COUNT(*) AS n FROM talent_pool WHERE {$condition}", $parameters)[0]['n'] ?? 0);
        $rows = $this->select(
            sprintf('SELECT ' . self::COLUMNS . ' FROM talent_pool WHERE %s ORDER BY created_at DESC, id LIMIT %d OFFSET %d', $condition, $perPage, ($page - 1) * $perPage),
            $parameters,
        );

        return ['items' => $rows, 'total' => $total];
    }

    public function delete(string $id): int
    {
        return $this->write('DELETE FROM talent_pool WHERE id = :id', ['id' => $id]);
    }

    public function deleteForApplication(string $applicationId): int
    {
        return $this->write('DELETE FROM talent_pool WHERE application_id = :id', ['id' => $applicationId]);
    }
}
