<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\Role;
use Paxofi\CorporateWebsite\Application\Freshness\ContentReviewStore;

/** content_reviews (migration 020, D-025) and the live content it covers. */
final class PdoContentReviews implements ContentReviewStore
{
    use GuardedQueries;

    private const LIVE = "lifecycle_state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP";

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function liveItems(): array
    {
        $rows = $this->select(
            "SELECT 'product' AS type, id AS item_key, name AS title, slug, updated_at AS changed_at FROM products WHERE " . self::LIVE . "
             UNION ALL SELECT 'service', id, name, slug, updated_at FROM services WHERE " . self::LIVE . "
             UNION ALL SELECT 'industry', id, name, slug, updated_at FROM industries WHERE " . self::LIVE . "
             UNION ALL SELECT 'article', id, title, slug, updated_at FROM articles
                       WHERE state = 'published' AND published_at IS NOT NULL AND published_at <= CURRENT_TIMESTAMP
             UNION ALL SELECT 'resource', id, COALESCE(NULLIF(title, ''), filename), NULL, updated_at FROM media_assets
                       WHERE lifecycle_state = 'active' AND kind = 'document' AND resource_listed_at IS NOT NULL",
        );

        return array_map(static fn (array $row): array => [
            'type' => (string) $row['type'],
            'key' => (string) $row['item_key'],
            'title' => (string) $row['title'],
            'slug' => $row['slug'] === null ? null : (string) $row['slug'],
            'changed_at' => self::time($row['changed_at']),
        ], $rows);
    }

    public function pagesPublishedAt(): array
    {
        $published = [];
        foreach ($this->select("SELECT page, MAX(created_at) AS published_at FROM page_revisions WHERE state = 'published' GROUP BY page") as $row) {
            $published[(string) $row['page']] = (string) self::time($row['published_at']);
        }

        return $published;
    }

    public function reviews(): array
    {
        $reviews = [];
        foreach ($this->select(
            'SELECT r.item_type, r.item_key, r.review_by, r.last_reviewed_at, r.last_reviewed_by, r.note, u.display_name AS reviewer
             FROM content_reviews r LEFT JOIN users u ON u.id = r.last_reviewed_by',
        ) as $row) {
            $reviews[$row['item_type'] . ':' . $row['item_key']] = [
                'review_by' => $row['review_by'] === null ? null : substr((string) $row['review_by'], 0, 10),
                'last_reviewed_at' => self::time($row['last_reviewed_at']),
                'last_reviewed_by_id' => $row['last_reviewed_by'] === null ? null : (string) $row['last_reviewed_by'],
                'last_reviewed_by' => $row['reviewer'] === null ? null : (string) $row['reviewer'],
                'note' => $row['note'] === null ? null : (string) $row['note'],
            ];
        }

        return $reviews;
    }

    public function save(string $type, string $key, ?string $reviewBy, ?string $lastReviewedAt, ?string $lastReviewedBy, ?string $note): void
    {
        $this->write(
            'INSERT INTO content_reviews (item_type, item_key, review_by, last_reviewed_at, last_reviewed_by, note)
             VALUES (:type, :item, :review_by, :reviewed_at, :reviewed_by, :note)
             ON DUPLICATE KEY UPDATE review_by = VALUES(review_by), last_reviewed_at = VALUES(last_reviewed_at),
                 last_reviewed_by = VALUES(last_reviewed_by), note = VALUES(note)',
            ['type' => $type, 'item' => $key, 'review_by' => $reviewBy, 'reviewed_at' => $lastReviewedAt, 'reviewed_by' => $lastReviewedBy, 'note' => $note],
        );
    }

    public function lastReminderAt(): ?string
    {
        $row = $this->select("SELECT MAX(created_at) AS sent_at FROM audit_events WHERE action = 'content_review.reminder_sent'")[0] ?? null;

        return self::time($row['sent_at'] ?? null);
    }

    public function administratorEmails(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['email'],
            $this->select(
                "SELECT DISTINCT u.email FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                 WHERE u.status = 'active' AND r.name = :role ORDER BY u.email",
                ['role' => Role::Administrator->value],
            ),
        );
    }

    private static function time(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
