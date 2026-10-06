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

    /** Lists the document on the Resources page (D-023), or takes it off when $listed is false (category and summary are kept). */
    public function setResource(string $id, ?string $category, ?string $summary, bool $listed): void;

    /** @return list<array<string, mixed>> active documents on the Resources page, newest listing first */
    public function listedResources(): array;

    /**
     * Products, services, industries and articles whose live content or draft uses each
     * file, and the Resources page for listed documents.
     *
     * @return array<string, list<string>> media id => names, e.g. "Paxofi Pay (product)"
     */
    public function usage(): array;
}
