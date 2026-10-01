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
        // Enquiries matching the email OR the IP, counted once each. A UNION of
        // two indexed lookups (idx_enquiries_email_created,
        // idx_enquiries_source_ip_created) keeps both indexes usable.
        $since = sprintf('created_at >= (CURRENT_TIMESTAMP - INTERVAL %d MINUTE)', $windowMinutes);
        $sql = "SELECT id FROM enquiries WHERE {$since} AND email = :email";
        $parameters = ['email' => $email];
        if ($clientIp !== null && $clientIp !== '') {
            $sql .= " UNION SELECT id FROM enquiries WHERE {$since} AND source_ip = :client_ip";
            $parameters['client_ip'] = $clientIp;
        }

        try {
            $rows = $this->database->reader()->fetchAll("SELECT COUNT(*) AS total FROM ({$sql}) recent", $parameters);
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }

        return (int) ($rows[0]['total'] ?? 0);
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
                    'user_agent' => $context->userAgent === null ? null : mb_substr(mb_scrub($context->userAgent, 'UTF-8'), 0, 500),
                    'request_id' => $context->requestId,
                ],
            );
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable('The enquiry could not be recorded.', $exception);
        }

        return $id;
    }
}
