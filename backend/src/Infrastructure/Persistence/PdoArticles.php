<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Articles\ArticleContent;
use Paxofi\CorporateWebsite\Application\Articles\ArticleEditor;
use Paxofi\CorporateWebsite\Application\Articles\Articles;

/** articles (migration 017, D-021). */
final class PdoArticles implements Articles
{
    use GuardedQueries;

    private const PUBLIC_COLUMNS = 'a.id, a.slug, a.category, a.title, a.summary, a.author_name, a.image_id, a.published_at, a.updated_at,
        m.filename AS image_filename, m.alt_text AS image_alt, m.width AS image_width, m.height AS image_height';

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function published(?string $category, int $page, int $perPage, DateTimeImmutable $now): array
    {
        $where = "a.state = 'published' AND a.published_at IS NOT NULL AND a.published_at <= :now";
        $parameters = ['now' => $now->format('Y-m-d H:i:s')];
        if ($category !== null) {
            $where .= ' AND a.category = :category';
            $parameters['category'] = $category;
        }
        $total = (int) ($this->select("SELECT COUNT(*) AS n FROM articles a WHERE {$where}", $parameters)[0]['n'] ?? 0);
        $items = $this->select(
            sprintf("SELECT %s FROM articles a LEFT JOIN media_assets m ON m.id = a.image_id AND m.kind = 'image' WHERE {$where} ORDER BY a.published_at DESC, a.id LIMIT %d OFFSET %d", self::PUBLIC_COLUMNS, $perPage, ($page - 1) * $perPage),
            $parameters,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function publishedBySlug(string $slug, DateTimeImmutable $now): ?array
    {
        return $this->select(
            'SELECT ' . self::PUBLIC_COLUMNS . ", a.body FROM articles a LEFT JOIN media_assets m ON m.id = a.image_id AND m.kind = 'image'
             WHERE a.slug = :slug AND a.state = 'published' AND a.published_at IS NOT NULL AND a.published_at <= :now",
            ['slug' => $slug, 'now' => $now->format('Y-m-d H:i:s')],
        )[0] ?? null;
    }

    public function all(): array
    {
        return $this->select('SELECT id, slug, state, category, title, published_at, updated_at, draft FROM articles ORDER BY updated_at DESC, id');
    }

    public function find(string $id): ?array
    {
        return $this->select(
            'SELECT a.*, u.display_name AS draft_author_name FROM articles a LEFT JOIN users u ON u.id = a.draft_author_id WHERE a.id = :id',
            ['id' => $id],
        )[0] ?? null;
    }

    public function slugExists(string $slug): bool
    {
        return $this->select('SELECT 1 FROM articles WHERE slug = :slug', ['slug' => $slug]) !== [];
    }

    public function create(string $id, string $slug, ArticleContent $content, string $authorId): void
    {
        $this->write(
            "INSERT INTO articles (id, slug, state, category, title, summary, body, author_name, image_id, draft, draft_saved_at, draft_author_id, created_by)
             VALUES (:id, :slug, 'draft', :category, :title, :summary, :body, :author, :image, :draft, CURRENT_TIMESTAMP, :draft_author, :created_by)",
            ['id' => $id, 'slug' => $slug, 'draft' => self::json($content), 'draft_author' => $authorId, 'created_by' => $authorId] + self::live($content),
        );
    }

    public function saveDraft(string $id, ArticleContent $content, string $authorId, DateTimeImmutable $now): void
    {
        $this->write(
            'UPDATE articles SET draft = :draft, draft_saved_at = :now, draft_author_id = :author WHERE id = :id',
            ['id' => $id, 'draft' => self::json($content), 'now' => $now->format('Y-m-d H:i:s'), 'author' => $authorId],
        );
        // An article never published has no live version: its listing shows the latest draft.
        $this->write(
            "UPDATE articles SET category = :category, title = :title, summary = :summary, body = :body, author_name = :author, image_id = :image WHERE id = :id AND published_at IS NULL",
            ['id' => $id] + self::live($content),
        );
    }

    public function discardDraft(string $id): void
    {
        $this->write('UPDATE articles SET draft = NULL, draft_saved_at = NULL, draft_author_id = NULL WHERE id = :id', ['id' => $id]);
    }

    public function publish(string $id, ArticleContent $content, DateTimeImmutable $now): void
    {
        $this->write(
            "UPDATE articles SET category = :category, title = :title, summary = :summary, body = :body, author_name = :author, image_id = :image,
                state = 'published', published_at = COALESCE(published_at, :now), draft = NULL, draft_saved_at = NULL, draft_author_id = NULL
             WHERE id = :id",
            ['id' => $id, 'now' => $now->format('Y-m-d H:i:s')] + self::live($content),
        );
    }

    public function setState(string $id, string $state): void
    {
        $this->write('UPDATE articles SET state = :state WHERE id = :id', ['id' => $id, 'state' => $state === ArticleEditor::PUBLISHED ? ArticleEditor::PUBLISHED : ArticleEditor::HIDDEN]);
    }

    public function delete(string $id): void
    {
        $this->write('DELETE FROM articles WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, ?string> */
    private static function live(ArticleContent $content): array
    {
        return ['category' => $content->category, 'title' => $content->title, 'summary' => $content->summary, 'body' => $content->body, 'author' => $content->authorName, 'image' => $content->imageId];
    }

    private static function json(ArticleContent $content): string
    {
        return json_encode($content->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
