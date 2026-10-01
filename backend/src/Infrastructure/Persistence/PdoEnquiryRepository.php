<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Contact\EnquiryRepository;
use Paxofi\CorporateWebsite\Application\Contact\EnquirySubmission;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

final class PdoEnquiryRepository implements EnquiryRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function countRecent(?string $clientIp, string $email, int $windowMinutes): int
    {
        // Two indexed lookups (idx_enquiries_source_ip_created, idx_enquiries_email_created)
        // instead of an OR that would defeat both indexes.
        $sql = 'SELECT COUNT(*) AS total FROM enquiries
                WHERE created_at >= (CURRENT_TIMESTAMP - INTERVAL %d MINUTE) AND %s = :value';

        try {
            $total = (int) ($this->database->reader()->fetchAll(sprintf($sql, $windowMinutes, 'email'), ['value' => $email])[0]['total'] ?? 0);
            if ($clientIp !== null && $clientIp !== '') {
                $byIp = (int) ($this->database->reader()->fetchAll(sprintf($sql, $windowMinutes, 'source_ip'), ['value' => $clientIp])[0]['total'] ?? 0);
                $total = max($total, $byIp);
            }
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }

        return $total;
    }

    public function add(EnquirySubmission $submission, RequestContext $context): string
    {
        $id = Uuid::v4();

        try {
            $this->database->writer()->execute(
                'INSERT INTO enquiries (id, name, email, company, message, status, source_ip, user_agent, request_id)
                 VALUES (:id, :name, :email, :company, :message, :status, :source_ip, :user_agent, :request_id)',
                [
                    'id' => $id,
                    'name' => $submission->name,
                    'email' => $submission->email,
                    'company' => $submission->company,
                    'message' => $submission->message,
                    'status' => 'new',
                    'source_ip' => $context->clientIp,
                    'user_agent' => $context->userAgent === null ? null : mb_substr($context->userAgent, 0, 500),
                    'request_id' => $context->requestId,
                ],
            );
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable('The enquiry could not be recorded.', $exception);
        }

        return $id;
    }
}
