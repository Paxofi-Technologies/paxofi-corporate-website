<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Pagination;
use Paxofi\CorporateWebsite\Application\RequestContext;

/** The enquiries inbox for Business Development and administrators. */
final class AdminEnquiryService
{
    public function __construct(
        private readonly AdminEnquiryRepository $enquiries,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(array $query): array
    {
        $pagination = Pagination::fromQuery($query);
        $status = null;
        if (is_string($query['status'] ?? null) && $query['status'] !== '') {
            $status = EnquiryStatus::tryFrom($query['status']) ?? throw new ValidationFailed(['status' => 'Unknown status.'], 'Invalid filter.');
        }
        $text = is_string($query['q'] ?? null) ? trim($query['q']) : '';
        if (mb_strlen($text) > 100) {
            throw new ValidationFailed(['q' => 'Search for at most 100 characters.'], 'Invalid filter.');
        }

        $result = $this->enquiries->search($status, $text === '' ? null : $text, $pagination);

        return [
            'items' => $result['items'],
            'meta' => $pagination->meta($result['total']) + ['counts' => $this->enquiries->countByStatus()],
        ];
    }

    /** @return array<string, mixed> */
    public function get(string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $enquiry = self::isId($id) ? $this->enquiries->find($id) : null;
        if ($enquiry === null) {
            throw new ResourceNotFound('Enquiry not found.');
        }
        $this->audit->record(new AuditEvent('enquiry.viewed', AuditEvent::OUTCOME_SUCCESS, 'enquiry', $id, $staff->user->id, $context->requestId));

        return $enquiry;
    }

    /** @return array<string, mixed> */
    public function update(string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $status = is_string($input['status'] ?? null) ? EnquiryStatus::tryFrom($input['status']) : null;
        if ($status === null) {
            throw new ValidationFailed(['status' => 'Choose a status.'], 'Please choose a status.');
        }
        if (!self::isId($id)) {
            throw new ResourceNotFound('Enquiry not found.');
        }

        $this->transactions->transaction(function () use ($id, $status, $staff, $context): void {
            if (!$this->enquiries->updateStatus($id, $status)) {
                throw new ResourceNotFound('Enquiry not found.');
            }
            $this->audit->record(new AuditEvent('enquiry.status.' . $status->value, AuditEvent::OUTCOME_SUCCESS, 'enquiry', $id, $staff->user->id, $context->requestId));
        });

        return $this->enquiries->find($id) ?? throw new ResourceNotFound('Enquiry not found.');
    }

    private static function isId(string $id): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id) === 1;
    }
}
