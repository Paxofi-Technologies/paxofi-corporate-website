<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

interface MediaRepository
{
    /** @param array<string, mixed> $row */
    public function insert(array $row): void;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return list<array<string, mixed>> newest first */
    public function all(?MediaKind $kind): array;

    public function updateDetails(string $id, ?string $altText, ?string $title): void;

    public function delete(string $id): void;

    /**
     * Products and services whose live content or draft uses each file.
     *
     * @return array<string, list<string>> media id => names, e.g. "Paxofi Pay (product)"
     */
    public function usage(): array;
}
