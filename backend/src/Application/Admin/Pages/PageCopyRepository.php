<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Pages;

interface PageCopyRepository
{
    /** @return array{data: array<string, mixed>, created_at: string}|null the newest published version */
    public function published(string $page): ?array;

    /** @return array{data: array<string, mixed>, created_at: string, author_name: ?string}|null */
    public function draft(string $page): ?array;

    /** @param array<string, string> $data */
    public function saveDraft(string $page, array $data, string $authorId): void;

    public function deleteDraft(string $page): void;

    /** @param array<string, string> $data */
    public function addPublished(string $page, array $data, string $authorId): void;

    /** @return list<array{id: string, created_at: string, author_name: ?string}> newest first */
    public function history(string $page, int $limit = 20): array;

    /** @return array<string, mixed>|null a published version's text */
    public function revision(string $page, string $revisionId): ?array;

    /** @return array<string, array{published_at: ?string, has_draft: bool}> */
    public function overview(): array;
}
