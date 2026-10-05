<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Articles;

use Closure;
use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Media\MediaPath;

/** Published News & Insights articles for the website (D-021). */
final class ArticleService
{
    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(private readonly Articles $articles, private readonly Closure $clock)
    {
    }

    /**
     * @param array<string, mixed> $query page, per_page (1–30), category
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(array $query): array
    {
        $page = self::int($query['page'] ?? 1, 1, 1000, 'page');
        $perPage = self::int($query['per_page'] ?? 12, 1, 30, 'per_page');
        $category = is_string($query['category'] ?? null) && isset(ArticleContent::CATEGORIES[$query['category']]) ? $query['category'] : null;
        $result = $this->articles->published($category, $page, $perPage, ($this->clock)());

        return [
            'items' => array_map(static fn (array $row): array => self::present($row, false), $result['items']),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total'], 'total_pages' => max(1, (int) ceil($result['total'] / $perPage)), 'categories' => ArticleContent::CATEGORIES],
        ];
    }

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        $row = preg_match('/^[a-z0-9-]{1,170}$/', $slug) === 1 ? $this->articles->publishedBySlug($slug, ($this->clock)()) : null;

        return self::present($row ?? throw new ResourceNotFound('Article not found.'), true);
    }

    /** @return array<string, mixed> */
    private static function present(array $row, bool $withBody): array
    {
        $article = [
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'category' => (string) $row['category'],
            'category_label' => ArticleContent::CATEGORIES[$row['category']] ?? 'Insight',
            'summary' => (string) $row['summary'],
            'author_name' => $row['author_name'] === null ? null : (string) $row['author_name'],
            'published_at' => gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($row['published_at'] . ' UTC')),
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($row['updated_at'] . ' UTC')),
            'image' => ($row['image_filename'] ?? null) === null ? null : [
                'path' => MediaPath::for((string) $row['image_id'], (string) $row['image_filename']),
                'alt' => (string) ($row['image_alt'] ?? ''),
                'width' => (int) $row['image_width'],
                'height' => (int) $row['image_height'],
            ],
        ];

        return $withBody ? $article + ['body' => (string) $row['body']] : $article;
    }

    private static function int(mixed $value, int $min, int $max, string $name): int
    {
        if (is_string($value) && ctype_digit($value) && strlen($value) <= 6) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new ValidationFailed([$name => "Use a number from {$min} to {$max}."], 'Invalid query parameters.');
        }

        return $value;
    }
}
