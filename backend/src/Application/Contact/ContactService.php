<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Use case: a visitor submits a public form (currently only "contact").
 *
 * Orchestrates validation, abuse controls, persistence and auditing. The
 * enquiry, its audit event and the staff email alert (D-016) are written
 * atomically; the alert is then sent from the outbox.
 */
final class ContactService
{
    public const FORM_KEY = 'contact';

    public function __construct(
        private readonly EnquiryValidator $validator,
        private readonly EnquiryRepository $enquiries,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Logger $logger,
        private readonly int $rateLimitMax = 5,
        private readonly int $rateLimitWindowMinutes = 10,
        private readonly ?Outbox $outbox = null,
        private readonly ?MailSettings $mail = null,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function submit(string $formKey, array $input, RequestContext $context): EnquiryReceipt
    {
        $formKey = strtolower(trim($formKey));
        if ($formKey !== self::FORM_KEY) {
            throw new ResourceNotFound('Form is not available.');
        }

        $submission = $this->validator->validate($input);

        if ($submission->isLikelySpam) {
            // Respond exactly as for a real submission so bots learn nothing.
            $this->logger->info('enquiry.honeypot_triggered', ['request_id' => $context->requestId]);

            return new EnquiryReceipt($formKey, persisted: false);
        }

        if ($this->enquiries->countRecent($context->clientIp, $submission->email, $this->rateLimitWindowMinutes) >= $this->rateLimitMax) {
            // Logged, not written to audit_events: a flood of blocked requests
            // must not turn into a flood of database writes.
            $this->logger->warning('enquiry.rate_limited', [
                'request_id' => $context->requestId,
                'client_ip' => $context->clientIp,
            ]);

            throw new RateLimited($this->rateLimitWindowMinutes * 60);
        }

        $this->transactions->transaction(function () use ($submission, $context): void {
            $id = $this->enquiries->add($submission, $context);
            $this->audit->record(new AuditEvent(
                action: 'enquiry.submitted',
                outcome: AuditEvent::OUTCOME_SUCCESS,
                targetType: 'enquiry',
                targetId: $id,
                requestId: $context->requestId,
            ));
            $alert = $this->outbox !== null && $this->mail !== null ? EnquiryAlert::email($this->mail, $id, $submission) : null;
            if ($alert !== null) {
                $this->outbox->add($alert);
            }
        });

        return new EnquiryReceipt($formKey, persisted: true);
    }
}
