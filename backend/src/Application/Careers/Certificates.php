<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use DateTimeImmutable;

/** certificates (migration 023, D-029). */
interface Certificates
{
    /** @param array<string, mixed> $certificate */
    public function add(array $certificate): void;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array;

    /** @return list<array<string, mixed>> newest first */
    public function forApplication(string $applicationId): array;

    public function revoke(string $id, string $reason, DateTimeImmutable $now): int;

    public function delete(string $id): int;
}
