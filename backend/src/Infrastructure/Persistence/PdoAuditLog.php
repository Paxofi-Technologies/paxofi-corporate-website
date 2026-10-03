<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\AuditLog;
use Paxofi\CorporateWebsite\Application\Pagination;

final class PdoAuditLog implements AuditLog
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function search(?string $actionPrefix, Pagination $pagination): array
    {
        $condition = '';
        $parameters = [];
        if ($actionPrefix !== null) {
            $condition = 'WHERE a.action LIKE :action';
            $parameters['action'] = addcslashes($actionPrefix, '%_\\') . '%';
        }
        $total = (int) ($this->select("SELECT COUNT(*) AS total FROM audit_events a {$condition}", $parameters)[0]['total'] ?? 0);
        $items = $this->select(
            sprintf(
                'SELECT a.id, a.action, a.outcome, a.target_type, a.target_id, a.request_id, a.created_at,
                        a.actor_id, u.display_name AS actor_name, u.email AS actor_email
                 FROM audit_events a LEFT JOIN users u ON u.id = a.actor_id %s
                 ORDER BY a.created_at DESC, a.id LIMIT %d OFFSET %d',
                $condition,
                $pagination->perPage,
                $pagination->offset(),
            ),
            $parameters,
        );

        return ['items' => $items, 'total' => $total];
    }
}
