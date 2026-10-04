<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** career_opportunities (migrations 001 and 014). */
interface CareerRoles
{
    /** @return list<CareerRole> published roles, in display order */
    public function published(): array;

    /** @return list<CareerRole> every role, for the staff area */
    public function all(): array;

    public function bySlug(string $slug): ?CareerRole;

    public function find(string $id): ?CareerRole;

    /** @param array<string, mixed> $values validated by RoleInput */
    public function create(string $id, string $slug, array $values): void;

    /** @param array<string, mixed> $values validated by RoleInput */
    public function update(string $id, array $values): void;

    public function setState(string $id, string $state): void;
}
