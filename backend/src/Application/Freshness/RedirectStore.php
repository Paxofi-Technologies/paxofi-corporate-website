<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

/** The redirects table (migration 020, D-025). */
interface RedirectStore
{
    /** @return list<array{id: string, from_path: string, to_path: string, note: string|null, created_by: string|null, created_at: string, updated_at: string}> */
    public function all(): array;

    /** @return array{id: string, from_path: string, to_path: string, note: string|null, created_by: string|null, created_at: string, updated_at: string}|null */
    public function find(string $id): ?array;

    public function create(string $id, string $from, string $to, ?string $note, string $createdBy): void;

    public function update(string $id, string $from, string $to, ?string $note): void;

    public function delete(string $id): void;

    /** @return list<string> addresses of industry and article pages that are live now */
    public function livePaths(): array;
}
