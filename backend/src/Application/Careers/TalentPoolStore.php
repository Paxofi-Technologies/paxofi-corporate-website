<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** talent_pool (migration 023, D-029). */
interface TalentPoolStore
{
    /** @param array<string, mixed> $entry */
    public function add(array $entry): void;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array;

    /** @return array<string, mixed>|null the entry made from this application */
    public function forApplication(string $applicationId): ?array;

    /**
     * @param array{q?: string, engagement?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function search(array $filters, int $page, int $perPage): array;

    public function delete(string $id): int;

    public function deleteForApplication(string $applicationId): int;
}
