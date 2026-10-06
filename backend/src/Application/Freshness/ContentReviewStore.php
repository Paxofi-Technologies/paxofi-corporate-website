<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

/** Live content and its review dates (migration 020, D-025). */
interface ContentReviewStore
{
    /**
     * Items shown on the website now, other than pages: published products,
     * services, industries and articles, and documents on the Resources page.
     *
     * @return list<array{type: string, key: string, title: string, slug: string|null, changed_at: string|null}>
     */
    public function liveItems(): array;

    /** @return array<string, string> page key => when its text was last published */
    public function pagesPublishedAt(): array;

    /**
     * @return array<string, array{review_by: string|null, last_reviewed_at: string|null, last_reviewed_by_id: string|null, last_reviewed_by: string|null, note: string|null}>
     *         keyed "type:key"
     */
    public function reviews(): array;

    /** @param string|null $lastReviewedBy a user id */
    public function save(string $type, string $key, ?string $reviewBy, ?string $lastReviewedAt, ?string $lastReviewedBy, ?string $note): void;

    /** When the last weekly reminder was sent, or null. */
    public function lastReminderAt(): ?string;

    /** @return list<string> email addresses of active administrators */
    public function administratorEmails(): array;
}
