<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** application_reviewers (migration 023, D-029). */
interface ApplicationReviewers
{
    public const ASSIGNED = 'assigned';
    public const RECUSED = 'recused';

    /** @return list<array{user_id: string, name: string, status: string, reason: ?string, created_at: string}> */
    public function forApplication(string $applicationId): array;

    /** @return array{status: string}|null */
    public function find(string $applicationId, string $userId): ?array;

    public function assign(string $id, string $applicationId, string $userId, ?string $assignedBy): void;

    /** Removes an assignment that has not been recused. */
    public function remove(string $applicationId, string $userId): int;

    /** Records a recusal, creating the row when the person was never assigned. */
    public function recuse(string $id, string $applicationId, string $userId, string $reason): void;

    public function emailOf(string $userId): ?string;

    /** @return list<array{id: string, name: string}> active staff who may see applications */
    public function eligibleStaff(): array;
}
