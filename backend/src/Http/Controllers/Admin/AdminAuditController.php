<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http\Controllers\Admin;

use Paxofi\Core\Contracts\HttpRequest;
use Paxofi\Core\Contracts\HttpResponse;
use Paxofi\CorporateWebsite\Application\Admin\AuditLog;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Pagination;
use Paxofi\CorporateWebsite\Http\AdminGuard;
use Paxofi\CorporateWebsite\Http\ApiResponse;

/** GET /api/v1/admin/audit?action=&page=&per_page= */
final class AdminAuditController
{
    public function __construct(private readonly AuditLog $audit, private readonly AdminGuard $guard)
    {
    }

    public function list(HttpRequest $request): HttpResponse
    {
        $this->guard->require($request, Permission::AUDIT_READ);
        $query = $request->query();
        $pagination = Pagination::fromQuery($query);
        $action = is_string($query['action'] ?? null) ? trim($query['action']) : '';
        if ($action !== '' && preg_match('/^[a-z_.]{1,60}$/', $action) !== 1) {
            throw new ValidationFailed(['action' => 'Use letters, dots and underscores only.'], 'Invalid filter.');
        }
        $result = $this->audit->search($action === '' ? null : $action, $pagination);

        return ApiResponse::success($request, $result['items'], $pagination->meta($result['total']));
    }
}
