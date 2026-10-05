<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Articles;

use DateTimeImmutable;

/** articles (migration 017, D-021). */
interface Articles
{
    /**
     * Published articles, newest first, with their picture's file details.
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function published(?string $category, int $page, int $perPage, DateTimeImmutable $now): array;

    /** @return array<string, mixed>|null a published article by its address */
    public function publishedBySlug(string $slug, DateTimeImmutable $now): ?array;

    /** @return list<array<string, mixed>> every article, newest change first (staff area) */
    public function all(): array;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    public function slugExists(string $slug): bool;

    /** Creates an unpublished article whose content is all in its draft. */
    public function create(string $id, string $slug, ArticleContent $content, string $authorId): void;

    public function saveDraft(string $id, ArticleContent $content, string $authorId, DateTimeImmutable $now): void;

    public function discardDraft(string $id): void;

    /** Copies the draft to the live content, clears the draft and shows the article (first publication date kept). */
    public function publish(string $id, ArticleContent $content, DateTimeImmutable $now): void;

    public function setState(string $id, string $state): void;

    public function delete(string $id): void;
}
