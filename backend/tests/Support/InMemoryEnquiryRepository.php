<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Support;

use Paxofi\CorporateWebsite\Application\Contact\EnquiryRepository;
use Paxofi\CorporateWebsite\Application\Contact\EnquirySubmission;
use Paxofi\CorporateWebsite\Application\RequestContext;

final class InMemoryEnquiryRepository implements EnquiryRepository
{
    /** @var list<array{submission: EnquirySubmission, context: RequestContext}> */
    public array $stored = [];
    public int $recentCount = 0;

    public function countRecent(?string $clientIp, string $email, int $windowMinutes): int
    {
        return $this->recentCount;
    }

    public function add(EnquirySubmission $submission, RequestContext $context): string
    {
        $this->stored[] = ['submission' => $submission, 'context' => $context];

        return 'enquiry-' . count($this->stored);
    }
}
