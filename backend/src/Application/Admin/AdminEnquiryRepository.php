<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Paxofi\CorporateWebsite\Application\Pagination;

interface AdminEnquiryRepository
{
    /** @return array{items: list<array<string, mixed>>, total: int} newest first */
    public function search(?EnquiryStatus $status, ?string $text, Pagination $pagination): array;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    /** @return array<string, int> count per status */
    public function countByStatus(): array;

    /** @return bool false when no enquiry has this id */
    public function updateStatus(string $id, EnquiryStatus $status): bool;
}
