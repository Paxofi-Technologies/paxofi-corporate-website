<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Careers\CareerRole;
use Paxofi\CorporateWebsite\Application\Careers\CareerRoles;

/** career_opportunities (migrations 001 and 014, D-018). */
final class PdoCareerRoles implements CareerRoles
{
    use GuardedQueries;

    private const COLUMNS = 'id, slug, code, title, family, summary, description, content, lifecycle_state, sort_order, published_at, updated_at';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function published(): array
    {
        return array_map(self::role(...), $this->select('SELECT ' . self::COLUMNS . " FROM career_opportunities WHERE lifecycle_state = 'published' ORDER BY sort_order, title"));
    }

    public function all(): array
    {
        return array_map(self::role(...), $this->select('SELECT ' . self::COLUMNS . ' FROM career_opportunities ORDER BY sort_order, title'));
    }

    public function bySlug(string $slug): ?CareerRole
    {
        $row = $this->select('SELECT ' . self::COLUMNS . ' FROM career_opportunities WHERE slug = :slug', ['slug' => $slug])[0] ?? null;

        return $row === null ? null : self::role($row);
    }

    public function find(string $id): ?CareerRole
    {
        $row = $this->select('SELECT ' . self::COLUMNS . ' FROM career_opportunities WHERE id = :id', ['id' => $id])[0] ?? null;

        return $row === null ? null : self::role($row);
    }

    public function create(string $id, string $slug, array $values): void
    {
        $this->write(
            "INSERT INTO career_opportunities (id, slug, title, description, lifecycle_state, code, family, summary, content, sort_order)
             VALUES (:id, :slug, :title, :purpose, 'draft', :code, :family, :summary, :content, :sort)",
            ['id' => $id, 'slug' => $slug] + self::parameters($values),
        );
    }

    public function update(string $id, array $values): void
    {
        $this->write(
            'UPDATE career_opportunities SET title = :title, description = :purpose, code = :code, family = :family, summary = :summary, content = :content, sort_order = :sort WHERE id = :id',
            ['id' => $id] + self::parameters($values),
        );
    }

    public function setState(string $id, string $state): void
    {
        $this->write(
            "UPDATE career_opportunities SET lifecycle_state = :state, published_at = CASE WHEN :state2 = 'published' THEN COALESCE(published_at, CURRENT_TIMESTAMP) ELSE published_at END WHERE id = :id",
            ['id' => $id, 'state' => $state, 'state2' => $state],
        );
    }

    /** @return array<string, mixed> */
    private static function parameters(array $values): array
    {
        return [
            'title' => $values['title'], 'purpose' => $values['purpose'], 'code' => $values['code'], 'family' => $values['family'],
            'summary' => $values['summary'], 'sort' => $values['sort_order'],
            'content' => json_encode([
                'responsibilities' => $values['responsibilities'], 'deliverables' => $values['deliverables'], 'competencies' => $values['competencies'],
                'tools' => $values['tools'], 'evidence' => $values['evidence'], 'assessment' => $values['assessment'], 'interview' => $values['interview'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function role(array $row): CareerRole
    {
        $content = is_string($row['content'] ?? null) ? json_decode($row['content'], true) : null;
        $content = is_array($content) ? $content : [];
        $list = static fn (string $key): array => array_values(array_filter(is_array($content[$key] ?? null) ? $content[$key] : [], 'is_string'));
        $text = static fn (string $key): string => is_string($content[$key] ?? null) ? $content[$key] : '';

        return new CareerRole(
            (string) $row['id'],
            (string) $row['slug'],
            (string) ($row['code'] ?? '') ?: strtoupper(substr((string) $row['title'], 0, 2)),
            (string) $row['title'],
            (string) ($row['family'] ?? ''),
            (string) ($row['summary'] ?? '') ?: mb_substr((string) $row['description'], 0, 300),
            (string) $row['description'],
            ['responsibilities' => $list('responsibilities'), 'deliverables' => $list('deliverables'), 'competencies' => $list('competencies'), 'tools' => $list('tools'), 'evidence' => $text('evidence'), 'assessment' => $text('assessment'), 'interview' => $text('interview')],
            (string) $row['lifecycle_state'],
            (int) ($row['sort_order'] ?? 100),
            $row['published_at'] === null ? null : (string) $row['published_at'],
            $row['updated_at'] === null ? null : (string) $row['updated_at'],
        );
    }
}
