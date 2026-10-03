<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Admin\AdminEnquiryRepository;
use Paxofi\CorporateWebsite\Application\Admin\EnquiryStatus;
use Paxofi\CorporateWebsite\Application\Pagination;

final class PdoAdminEnquiryRepository implements AdminEnquiryRepository
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function search(?EnquiryStatus $status, ?string $text, Pagination $pagination): array
    {
        $where = [];
        $parameters = [];
        if ($status !== null) {
            $where[] = 'status = :status';
            $parameters['status'] = $status->value;
        }
        if ($text !== null) {
            $where[] = '(name LIKE :t1 OR email LIKE :t2 OR company LIKE :t3)';
            $like = '%' . addcslashes($text, '%_\\') . '%';
            $parameters += ['t1' => $like, 't2' => $like, 't3' => $like];
        }
        $condition = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int) ($this->select("SELECT COUNT(*) AS total FROM enquiries {$condition}", $parameters)[0]['total'] ?? 0);
        $items = $this->select(
            sprintf(
                'SELECT id, name, email, company, LEFT(message, 160) AS excerpt, status, created_at FROM enquiries %s ORDER BY created_at DESC, id LIMIT %d OFFSET %d',
                $condition,
                $pagination->perPage,
                $pagination->offset(),
            ),
            $parameters,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function find(string $id): ?array
    {
        $rows = $this->select(
            'SELECT id, name, email, company, message, status, source_ip, user_agent, request_id, created_at FROM enquiries WHERE id = :id',
            ['id' => $id],
        );

        return $rows[0] ?? null;
    }

    public function countByStatus(): array
    {
        $counts = array_fill_keys(array_map(static fn (EnquiryStatus $s): string => $s->value, EnquiryStatus::cases()), 0);
        foreach ($this->select('SELECT status, COUNT(*) AS total FROM enquiries GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function updateStatus(string $id, EnquiryStatus $status): bool
    {
        if ($this->find($id) === null) {
            return false;
        }
        $this->write('UPDATE enquiries SET status = :status WHERE id = :id', ['id' => $id, 'status' => $status->value]);

        return true;
    }
}
