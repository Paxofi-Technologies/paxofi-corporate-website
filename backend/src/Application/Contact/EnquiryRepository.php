<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

use Paxofi\CorporateWebsite\Application\RequestContext;

interface EnquiryRepository
{
    /** Number of enquiries in the last $windowMinutes from this IP or email address. */
    public function countRecent(?string $clientIp, string $email, int $windowMinutes): int;

    /** Persists the enquiry and returns its generated identifier. */
    public function add(EnquirySubmission $submission, RequestContext $context): string;
}
