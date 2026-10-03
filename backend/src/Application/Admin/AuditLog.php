<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Paxofi\CorporateWebsite\Application\Pagination;

/** Read access to audit events, newest first, with the acting staff member's name. */
interface AuditLog
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function search(?string $actionPrefix, Pagination $pagination): array;
}
