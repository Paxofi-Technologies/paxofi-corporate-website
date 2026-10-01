<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

/** Validated, normalised contact enquiry (DTO). Construct via EnquiryValidator. */
final readonly class EnquirySubmission
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $company,
        public string $message,
        public bool $isLikelySpam = false,
    ) {
    }
}
