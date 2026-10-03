<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Catalog;

interface CatalogEditorRepository
{
    /** @return list<array<string, mixed>> live rows with has_draft, ordered for display */
    public function all(CatalogKind $kind): array;

    /** @return array<string, mixed>|null live row */
    public function find(CatalogKind $kind, string $id): ?array;

    public function slugExists(CatalogKind $kind, string $slug): bool;

    /** Inserts a hidden item and returns its id. */
    public function insert(CatalogKind $kind, string $slug, CatalogContent $content): string;

    public function updateLive(CatalogKind $kind, string $id, CatalogContent $content): void;

    public function setVisible(CatalogKind $kind, string $id, bool $visible): void;

    /** @return array{id: string, data: array<string, mixed>, author_name: ?string, created_at: string}|null */
    public function draft(CatalogKind $kind, string $id): ?array;

    public function saveDraft(CatalogKind $kind, string $id, CatalogContent $content, string $authorId): void;

    public function deleteDraft(CatalogKind $kind, string $id): void;

    /** Turns the draft into a published revision. */
    public function markDraftPublished(CatalogKind $kind, string $id, string $authorId): void;

    public function addRevision(CatalogKind $kind, string $id, string $state, CatalogContent $content, string $authorId): void;

    /** @return list<array{id: string, state: string, data: array<string, mixed>, author_name: ?string, created_at: string}> newest first */
    public function revisions(CatalogKind $kind, string $id, int $limit = 20): array;

    /** @return array{id: string, state: string, data: array<string, mixed>}|null */
    public function revision(CatalogKind $kind, string $id, string $revisionId): ?array;
}
