<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Careers\ApplicationStage;
use Paxofi\CorporateWebsite\Application\Careers\JobApplications;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/** job_applications, application_notes and application_uploads (migration 014, D-019). */
final class PdoJobApplications implements JobApplications
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function add(string $id, string $reference, string $roleId, array $values, ?array $cv, RequestContext $context): void
    {
        $this->write(
            'INSERT INTO job_applications (id, reference, opportunity_id, full_name, email, phone, location, hours_per_week, portfolio_url, linkedin_url, motivation, experience,
                 cv_reference, cv_filename, cv_media_type, cv_size, privacy_version, source_ip, user_agent, source, utm_source, utm_medium, utm_campaign)
             VALUES (:id, :reference, :role, :name, :email, :phone, :location, :hours, :portfolio, :linkedin, :motivation, :experience,
                 :cv, :cv_name, :cv_type, :cv_size, :privacy, :ip, :ua, :source, :utm_source, :utm_medium, :utm_campaign)',
            [
                'id' => $id, 'reference' => $reference, 'role' => $roleId, 'name' => $values['full_name'], 'email' => $values['email'],
                'phone' => $values['phone'], 'location' => $values['location'], 'hours' => $values['hours_per_week'],
                'portfolio' => $values['portfolio_url'], 'linkedin' => $values['linkedin_url'], 'motivation' => $values['motivation'], 'experience' => $values['experience'],
                'cv' => $cv['file_reference'] ?? null, 'cv_name' => $cv['filename'] ?? null, 'cv_type' => $cv['media_type'] ?? null, 'cv_size' => $cv['size_bytes'] ?? null,
                'privacy' => \Paxofi\CorporateWebsite\Application\Careers\ApplicationInput::PRIVACY_VERSION,
                'ip' => $context->clientIp, 'ua' => $context->userAgent === null ? null : mb_substr($context->userAgent, 0, 255),
                'source' => $values['source'] ?? null, 'utm_source' => $values['utm_source'] ?? null, 'utm_medium' => $values['utm_medium'] ?? null, 'utm_campaign' => $values['utm_campaign'] ?? null,
            ],
        );
    }

    public function referenceExists(string $reference): bool
    {
        return $this->select('SELECT 1 FROM job_applications WHERE reference = :r', ['r' => $reference]) !== [];
    }

    public function recentCounts(?string $clientIp, string $email, DateTimeImmutable $now): array
    {
        $hour = $now->modify('-1 hour')->format('Y-m-d H:i:s');
        $day = $now->modify('-1 day')->format('Y-m-d H:i:s');
        $ip = $clientIp === null ? 0 : (int) ($this->select('SELECT COUNT(*) AS n FROM job_applications WHERE source_ip = :ip AND created_at >= :since', ['ip' => $clientIp, 'since' => $hour])[0]['n'] ?? 0);
        $byEmail = (int) ($this->select('SELECT COUNT(*) AS n FROM job_applications WHERE email = :email AND created_at >= :since', ['email' => $email, 'since' => $day])[0]['n'] ?? 0);

        return ['ip' => $ip, 'email' => $byEmail];
    }

    public function hasOpenApplication(string $email, string $roleId): bool
    {
        return $this->select(
            "SELECT 1 FROM job_applications WHERE email = :email AND opportunity_id = :role AND stage NOT IN ('declined', 'withdrawn', 'rejected') LIMIT 1",
            ['email' => $email, 'role' => $roleId],
        ) !== [];
    }

    public function addUpload(string $tokenHash, string $fileReference, string $filename, string $mediaType, int $size, ?string $clientIp): void
    {
        $this->write(
            'INSERT INTO application_uploads (token_hash, file_reference, filename, media_type, size_bytes, source_ip) VALUES (:hash, :ref, :name, :type, :size, :ip)',
            ['hash' => $tokenHash, 'ref' => $fileReference, 'name' => $filename, 'type' => $mediaType, 'size' => $size, 'ip' => $clientIp],
        );
    }

    public function recentUploads(?string $clientIp, DateTimeImmutable $now): int
    {
        return $clientIp === null ? 0 : (int) ($this->select('SELECT COUNT(*) AS n FROM application_uploads WHERE source_ip = :ip AND created_at >= :since', ['ip' => $clientIp, 'since' => $now->modify('-1 hour')->format('Y-m-d H:i:s')])[0]['n'] ?? 0);
    }

    public function claimUpload(string $tokenHash, DateTimeImmutable $now): ?array
    {
        $claimed = $this->write(
            'UPDATE application_uploads SET claimed_at = :now WHERE token_hash = :hash AND claimed_at IS NULL AND created_at >= :since',
            ['hash' => $tokenHash, 'now' => $now->format('Y-m-d H:i:s'), 'since' => $now->modify('-1 day')->format('Y-m-d H:i:s')],
        );
        if ($claimed !== 1) {
            return null;
        }
        $row = $this->select('SELECT file_reference, filename, media_type, size_bytes FROM application_uploads WHERE token_hash = :hash', ['hash' => $tokenHash])[0];

        return ['file_reference' => (string) $row['file_reference'], 'filename' => (string) $row['filename'], 'media_type' => (string) $row['media_type'], 'size_bytes' => (int) $row['size_bytes']];
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        $where = ['1 = 1'];
        $parameters = [];
        if (isset($filters['role'])) {
            $where[] = 'a.opportunity_id = :role';
            $parameters['role'] = $filters['role'];
        }
        if (isset($filters['q'])) {
            $where[] = '(a.full_name LIKE :q1 OR a.email LIKE :q2 OR a.reference LIKE :q3)';
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $parameters += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $counts = [];
        foreach ($this->select('SELECT a.stage, COUNT(*) AS n FROM job_applications a WHERE ' . implode(' AND ', $where) . ' GROUP BY a.stage', $parameters) as $row) {
            $counts[(string) $row['stage']] = (int) $row['n'];
        }
        if (isset($filters['stage'])) {
            $where[] = 'a.stage = :stage';
            $parameters['stage'] = $filters['stage'];
        }
        $condition = implode(' AND ', $where);
        $total = (int) ($this->select("SELECT COUNT(*) AS n FROM job_applications a WHERE {$condition}", $parameters)[0]['n'] ?? 0);
        $rows = $this->select(
            sprintf(
                "SELECT a.id, a.reference, a.full_name, a.email, a.stage, a.created_at, a.evidence_scores, a.interview_scores, a.cv_reference, o.title AS role_title
                 FROM job_applications a JOIN career_opportunities o ON o.id = a.opportunity_id
                 WHERE {$condition} ORDER BY a.created_at DESC, a.id LIMIT %d OFFSET %d",
                $perPage,
                ($page - 1) * $perPage,
            ),
            $parameters,
        );

        return ['items' => array_map(self::decode(...), $rows), 'total' => $total, 'counts' => $counts];
    }

    public function find(string $id): ?array
    {
        $row = $this->select(
            'SELECT a.*, o.title AS role_title, o.slug AS role_slug FROM job_applications a JOIN career_opportunities o ON o.id = a.opportunity_id WHERE a.id = :id',
            ['id' => $id],
        )[0] ?? null;

        return $row === null ? null : self::decode($row);
    }

    public function setStage(string $id, ApplicationStage $stage, DateTimeImmutable $now): void
    {
        $this->write(
            "UPDATE job_applications SET stage = :stage, stage_changed_at = :now, closed_at = :closed,
                first_reviewed_at = CASE WHEN first_reviewed_at IS NULL AND :stage2 <> 'applied' THEN :now2 ELSE first_reviewed_at END
             WHERE id = :id",
            ['id' => $id, 'stage' => $stage->value, 'stage2' => $stage->value, 'now' => $now->format('Y-m-d H:i:s'), 'now2' => $now->format('Y-m-d H:i:s'), 'closed' => $stage->isClosed() ? $now->format('Y-m-d H:i:s') : null],
        );
    }

    public function setScores(string $id, string $gate, array $scores): void
    {
        $column = $gate === 'interview' ? 'interview_scores' : 'evidence_scores';
        $this->write("UPDATE job_applications SET {$column} = :scores WHERE id = :id", ['id' => $id, 'scores' => json_encode($scores, JSON_THROW_ON_ERROR)]);
    }

    public function addNote(string $applicationId, ?string $authorId, string $kind, string $body): void
    {
        $this->write(
            'INSERT INTO application_notes (id, application_id, author_id, kind, body) VALUES (:id, :application, :author, :kind, :body)',
            ['id' => Uuid::v4(), 'application' => $applicationId, 'author' => $authorId, 'kind' => $kind, 'body' => $body],
        );
    }

    public function notes(string $applicationId): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'kind' => (string) $row['kind'],
            'body' => (string) $row['body'],
            'author_name' => $row['author_name'] === null ? null : (string) $row['author_name'],
            'created_at' => substr((string) $row['created_at'], 0, 19),
        ], $this->select(
            'SELECT n.id, n.kind, n.body, n.created_at, u.display_name AS author_name FROM application_notes n LEFT JOIN users u ON u.id = n.author_id
             WHERE n.application_id = :id ORDER BY n.created_at DESC, n.id DESC',
            ['id' => $applicationId],
        ));
    }

    public function delete(string $id): void
    {
        $this->write('DELETE FROM job_applications WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed> */
    private static function decode(array $row): array
    {
        foreach (['evidence_scores', 'interview_scores'] as $key) {
            if (array_key_exists($key, $row)) {
                $decoded = is_string($row[$key]) ? json_decode($row[$key], true) : null;
                $row[$key] = is_array($decoded) ? array_map('intval', $decoded) : null;
            }
        }

        return $row;
    }

    public function report(?DateTimeImmutable $since): array
    {
        $where = $since === null ? '1 = 1' : 'a.created_at >= :since';
        $parameters = $since === null ? [] : ['since' => $since->format('Y-m-d H:i:s')];
        $group = fn (string $expression): array => array_map(
            static fn (array $row): array => ['key' => $row['k'] === null ? null : (string) $row['k'], 'count' => (int) $row['n']],
            $this->select("SELECT {$expression} AS k, COUNT(*) AS n FROM job_applications a JOIN career_opportunities o ON o.id = a.opportunity_id WHERE {$where} GROUP BY k ORDER BY n DESC, k", $parameters),
        );

        return [
            'total' => (int) ($this->select("SELECT COUNT(*) AS n FROM job_applications a WHERE {$where}", $parameters)[0]['n'] ?? 0),
            'by_role' => $group('o.title'),
            'by_stage' => $group('a.stage'),
            'by_source' => $group('a.source'),
            'by_campaign' => $group("CASE WHEN a.utm_source IS NULL AND a.utm_campaign IS NULL THEN NULL ELSE CONCAT_WS(' / ', a.utm_source, a.utm_medium, a.utm_campaign) END"),
            'by_day' => $group('DATE(a.created_at)'),
            'review_times' => array_map(
                static fn (array $row): array => ['created_at' => (string) $row['created_at'], 'first_reviewed_at' => $row['first_reviewed_at'] === null ? null : (string) $row['first_reviewed_at']],
                $this->select("SELECT a.created_at, a.first_reviewed_at FROM job_applications a WHERE {$where}", $parameters),
            ),
        ];
    }
}
